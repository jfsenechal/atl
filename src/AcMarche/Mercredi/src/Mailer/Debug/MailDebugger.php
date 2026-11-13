<?php

namespace AcMarche\Mercredi\Mailer\Debug;

use AcMarche\Mercredi\Organisation\Repository\OrganisationRepository;
use AcMarche\Mercredi\Organisation\Traits\OrganisationPropertyInitTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Mailer\Transport\TransportInterface as MailerTransportInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Messenger\Transport\TransportInterface as MessengerTransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Helps to find out why a mail does not arrive: shows the mailer configuration, the
 * Messenger queues holding mails, and sends test mails with the SMTP conversation.
 */
final class MailDebugger
{
    use OrganisationPropertyInitTrait;

    public function __construct(
        #[Autowire(env: 'MAILER_DSN')]
        private readonly string $mailerDsn,
        #[Autowire(service: 'mailer.transports')]
        private readonly MailerTransportInterface $mailerTransport,
        private readonly MailerInterface $mailer,
        #[Autowire(service: 'messenger.senders_locator')]
        private readonly SendersLocatorInterface $sendersLocator,
        #[Autowire(service: 'messenger.transport.async')]
        private readonly MessengerTransportInterface $asyncTransport,
        #[Autowire(service: 'messenger.transport.failed')]
        private readonly MessengerTransportInterface $failedTransport,
        private readonly KernelInterface $kernel,
    ) {
    }

    /**
     * The mailer DSN with the password hidden.
     */
    public function maskedDsn(): string
    {
        return preg_replace('#(://[^:/@]*:)[^@]*@#', '$1****@', $this->mailerDsn) ?? '';
    }

    /**
     * Unlike the trait, does not query the organisation when the service is built:
     * it is loaded on first use, so the page still opens when the database fails.
     */
    #[Required]
    public function setorganisationRepository(OrganisationRepository $organisationRepository): void
    {
        $this->organisationRepository = $organisationRepository;
    }

    public function senderAddress(): string
    {
        $this->loadOrganisation();

        return $this->getEmailSenderAddress();
    }

    public function contactAddress(): string
    {
        $this->loadOrganisation();

        return $this->getEmailContact();
    }

    private function loadOrganisation(): void
    {
        if (null === $this->organisation) {
            $this->setOrganisation();
        }
    }

    /**
     * Messenger transports a mail sent with MailerInterface goes to; empty means sent at once.
     *
     * @return string[]
     */
    public function mailRouting(): array
    {
        $envelope = new Envelope(new SendEmailMessage(new Email()));

        return array_keys(iterator_to_array($this->sendersLocator->getSenders($envelope)));
    }

    /**
     * @return array{count: ?int, messages: list<array<string, mixed>>, error: ?string}
     */
    public function queue(string $name, int $limit = 30): array
    {
        $transport = 'failed' === $name ? $this->failedTransport : $this->asyncTransport;
        $result = ['count' => null, 'messages' => [], 'error' => null];

        try {
            if ($transport instanceof MessageCountAwareInterface) {
                $result['count'] = $transport->getMessageCount();
            }
            if ($transport instanceof ListableReceiverInterface) {
                foreach ($transport->all($limit) as $envelope) {
                    $result['messages'][] = $this->describe($envelope);
                }
            }
        } catch (\Throwable $exception) {
            $result['error'] = $exception->getMessage();
        }

        return $result;
    }

    /**
     * Sends straight over SMTP (no Messenger queue) and returns the SMTP conversation.
     *
     * @return array{success: bool, error: ?string, debug: string}
     */
    public function sendDirect(Email $email): array
    {
        try {
            $sent = $this->mailerTransport->send($email);

            return ['success' => true, 'error' => null, 'debug' => $sent?->getDebug() ?? ''];
        } catch (TransportExceptionInterface $exception) {
            return ['success' => false, 'error' => $exception->getMessage(), 'debug' => $exception->getDebug()];
        }
    }

    /**
     * Sends the way the application does (MailerInterface, so through the queue if routed).
     *
     * @return array{success: bool, error: ?string, debug: string}
     */
    public function sendLikeTheApplication(Email $email): array
    {
        try {
            $this->mailer->send($email);
        } catch (\Throwable $exception) {
            return ['success' => false, 'error' => $exception->getMessage(), 'debug' => ''];
        }

        $routing = $this->mailRouting();

        return [
            'success' => true,
            'error' => null,
            'debug' => [] === $routing
                ? 'Envoyé directement (pas de file Messenger).'
                : 'Mis en file dans: '.implode(', ', $routing).'. Il ne partira que quand la file sera traitée.',
        ];
    }

    /**
     * Processes the mails waiting in the async queue, as messenger:consume would.
     */
    public function consumeQueue(int $limit = 20, int $timeLimit = 50): string
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $output = new BufferedOutput();
        $application->run(new ArrayInput([
            'command' => 'messenger:consume',
            'receivers' => ['async'],
            '--limit' => $limit,
            '--time-limit' => $timeLimit,
            '-vv' => true,
        ]), $output);

        return $output->fetch();
    }

    public function createTestEmail(string $from, string $to, string $subject): Email
    {
        return (new Email())
            ->from(new Address($from))
            ->to(new Address($to))
            ->subject($subject)
            ->text('Mail de test envoyé depuis la page de diagnostic le '.date('d/m/Y H:i:s').'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Envelope $envelope): array
    {
        $message = $envelope->getMessage();
        $email = $message instanceof SendEmailMessage ? $message->getMessage() : null;
        $error = $envelope->last(ErrorDetailsStamp::class);
        $redelivery = $envelope->last(RedeliveryStamp::class);

        return [
            'id' => $envelope->last(TransportMessageIdStamp::class)?->getId(),
            'type' => $message::class,
            'subject' => $email instanceof Email ? $email->getSubject() : null,
            'from' => $email instanceof Email ? implode(', ', array_map(static fn (Address $a) => $a->toString(), $email->getFrom())) : null,
            'to' => $email instanceof Email ? implode(', ', array_map(static fn (Address $a) => $a->toString(), $email->getTo())) : null,
            'error' => $error?->getExceptionMessage(),
            'retried_at' => $redelivery?->getRedeliveredAt(),
            'retry_count' => $redelivery?->getRetryCount(),
        ];
    }
}
