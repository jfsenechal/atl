<?php

namespace AcMarche\Mercredi\Mailer\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Sendmail (PHP sendmail_path) as used on OVH shared hosting.
 *
 * In OVH scheduled tasks, proc_close() cannot read the exit status of sendmail and
 * returns -1, although the mail was accepted and delivered. Symfony then throws
 * "Process failed with exit code -1: " and Messenger retries, so the mail is sent
 * several times. A real refusal also gives -1 but sendmail prints the reason
 * ("sendmail: fatal: ..."): only an empty output is treated as sent.
 */
final class OvhSendmailTransport extends AbstractTransport
{
    private const UNKNOWN_STATUS = 'Process failed with exit code -1:';

    public function __construct(
        private readonly TransportInterface $sendmail,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    public function __toString(): string
    {
        return 'ovh://'.$this->sendmail;
    }

    protected function doSend(SentMessage $message): void
    {
        try {
            $this->sendmail->send($message->getOriginalMessage(), $message->getEnvelope());
        } catch (TransportException $e) {
            if (self::UNKNOWN_STATUS !== trim($e->getMessage())) {
                throw $e;
            }
            $this->getLogger()->notice('sendmail exit status unknown (-1) and no output: mail considered sent.');
        }
    }
}
