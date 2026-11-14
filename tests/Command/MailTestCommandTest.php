<?php

namespace App\Tests\Command;

use AcMarche\Mercredi\Command\MailTestCommand;
use AcMarche\Mercredi\Mailer\BulkMailer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mime\Email;

final class MailTestCommandTest extends KernelTestCase
{
    /** @var list<Envelope> */
    private array $envelopes = [];

    public function testQueuesMailsOnTheAsyncTransport(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');

        $tester = $this->createTester($mailer);

        self::assertSame(Command::SUCCESS, $tester->execute(['to' => 'parent@example.com', '--queue' => true, '--count' => 3]));
        self::assertCount(3, $this->envelopes);
        foreach ($this->envelopes as $envelope) {
            self::assertSame(['async'], $envelope->last(TransportNamesStamp::class)->getTransportNames());
            $message = $envelope->getMessage();
            self::assertInstanceOf(SendEmailMessage::class, $message);
            self::assertSame('parent@example.com', $message->getMessage()->getTo()[0]->getAddress());
            self::assertSame('ecole@example.com', $message->getMessage()->getFrom()[0]->getAddress());
        }
    }

    public function testSendsDirectlyByDefault(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')
            ->with(self::callback(static fn (Email $email): bool => 'parent@example.com' === $email->getTo()[0]->getAddress()));

        self::assertSame(Command::SUCCESS, $this->createTester($mailer)->execute(['to' => 'parent@example.com']));
        self::assertSame([], $this->envelopes);
    }

    public function testDirectSendFailureIsAnError(): void
    {
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willThrowException(new TransportException('Connection refused'));

        $tester = $this->createTester($mailer);

        self::assertSame(Command::FAILURE, $tester->execute(['to' => 'parent@example.com']));
        self::assertStringContainsString('Connection refused', $tester->getDisplay());
    }

    private function createTester(MailerInterface $mailer): CommandTester
    {
        self::bootKernel();
        $envelopes = &$this->envelopes;
        $bus = new class($envelopes) implements MessageBusInterface {
            /** @param list<Envelope> $envelopes */
            public function __construct(private array &$envelopes)
            {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return $this->envelopes[] = Envelope::wrap($message, $stamps);
            }
        };
        $bulkMailer = new BulkMailer($bus, self::getContainer()->get(BodyRendererInterface::class));

        return new CommandTester(new MailTestCommand($mailer, $bulkMailer, 'ecole@example.com'));
    }
}
