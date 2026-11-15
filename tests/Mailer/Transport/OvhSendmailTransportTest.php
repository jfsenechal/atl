<?php

namespace App\Tests\Mailer\Transport;

use AcMarche\Mercredi\Mailer\Transport\OvhSendmailTransport;
use AcMarche\Mercredi\Mailer\Transport\OvhSendmailTransportFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class OvhSendmailTransportTest extends KernelTestCase
{
    public function testDsnIsResolvedByTheContainer(): void
    {
        self::bootKernel();
        /** @var Transport $transport */
        $transport = self::getContainer()->get('mailer.transport_factory');

        self::assertInstanceOf(OvhSendmailTransport::class, $transport->fromString('ovh://default'));
    }

    public function testExitCodeMinusOneWithoutOutputIsConsideredSent(): void
    {
        $transport = new OvhSendmailTransport($this->sendmailFailingWith('Process failed with exit code -1: '));

        self::assertInstanceOf(SentMessage::class, $transport->send($this->email()));
    }

    public function testRefusalPrintedBySendmailIsStillAnError(): void
    {
        $transport = new OvhSendmailTransport($this->sendmailFailingWith(
            'Process failed with exit code -1: sendmail: fatal: User visitmp(58520) is not allowed to submit mail',
        ));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('not allowed to submit mail');
        $transport->send($this->email());
    }

    public function testOtherExitCodesAreStillErrors(): void
    {
        $transport = new OvhSendmailTransport($this->sendmailFailingWith('Process failed with exit code 75: '));

        $this->expectException(TransportException::class);
        $transport->send($this->email());
    }

    public function testFactoryOnlySupportsOvhScheme(): void
    {
        $factory = new OvhSendmailTransportFactory();

        self::assertTrue($factory->supports(Transport\Dsn::fromString('ovh://default')));
        self::assertFalse($factory->supports(Transport\Dsn::fromString('native://default')));
    }

    private function sendmailFailingWith(string $error): TransportInterface
    {
        return new class($error) implements TransportInterface {
            public function __construct(private readonly string $error)
            {
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new TransportException($this->error);
            }

            public function __toString(): string
            {
                return 'fake';
            }
        };
    }

    private function email(): Email
    {
        return (new Email())->from('from@example.com')->to('to@example.com')->subject('Test')->text('Body');
    }
}
