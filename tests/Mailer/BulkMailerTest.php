<?php

namespace App\Tests\Mailer;

use AcMarche\Mercredi\Mailer\BulkMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Mime\BodyRendererInterface;

final class BulkMailerTest extends KernelTestCase
{
    public function testQueuesOneRenderedMailPerUniqueRecipientOnTheAsyncTransport(): void
    {
        self::bootKernel();
        $bus = new class implements MessageBusInterface {
            /** @var list<Envelope> */
            public array $envelopes = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->envelopes[] = Envelope::wrap($message, $stamps);
            }
        };
        $bulkMailer = new BulkMailer($bus, self::getContainer()->get(BodyRendererInterface::class));

        $email = (new TemplatedEmail())
            ->from('ecole@example.com')
            ->subject('Sortie')
            ->htmlTemplate('@AcMarcheMercrediEmail/admin/mail.html.twig')
            ->context(['texte' => 'Bonjour les parents', 'organisation' => null]);

        $count = $bulkMailer->queue($email, ['a@example.com', ' b@example.com', 'a@example.com', '']);

        self::assertSame(2, $count);
        self::assertCount(2, $bus->envelopes);
        $recipients = [];
        foreach ($bus->envelopes as $envelope) {
            self::assertSame(['async'], $envelope->last(TransportNamesStamp::class)->getTransportNames());
            $message = $envelope->getMessage();
            self::assertInstanceOf(SendEmailMessage::class, $message);
            $mail = $message->getMessage();
            self::assertInstanceOf(TemplatedEmail::class, $mail);
            self::assertStringContainsString('Bonjour les parents', $mail->getHtmlBody());
            self::assertSame([], $mail->getContext(), 'rendered mail must not carry entities to the queue');
            $recipients[] = $mail->getTo()[0]->getAddress();
        }
        self::assertSame(['a@example.com', 'b@example.com'], $recipients);
    }
}
