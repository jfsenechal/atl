<?php

namespace AcMarche\Mercredi\Mailer\Events;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;

/**
 * Logs on the "mail" channel: written to the log file but never mailed, otherwise a
 * failing SMTP server makes every failure send an error mail that fails in turn.
 */
#[WithMonologChannel('mail')]
class MailerEventsSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerInterface $mercredilogger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SentMessageEvent::class => 'onMessageSent',
            FailedMessageEvent::class => 'onMessageFailure',
        ];

    }

    public function onMessageSent(SentMessageEvent $event): void
    {
        $message = $event->getMessage();
        $recipients = $message->getEnvelope()->getRecipients();
        $to = '';
        foreach ($recipients as $recipient) {
            $to .= $recipient->toString();
        }
        $from = $message->getEnvelope()->getSender();
        $this->mercredilogger->info("[MERCREDI] Mail sent from ".$from->toString()." to ".$to);
    }

    public function onMessageFailure(FailedMessageEvent $event): void
    {
        $message = $event->getMessage();
        $this->mercredilogger->error("[MERCREDI] Mail error ".$message->toString());
    }
}