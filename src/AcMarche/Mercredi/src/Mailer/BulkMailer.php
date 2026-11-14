<?php

namespace AcMarche\Mercredi\Mailer;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Mime\BodyRendererInterface;

/**
 * Mails are sent directly by default (MailerInterface, no Messenger routing).
 * Mass mailings (a message to many parents...) go through this service instead: one mail
 * per recipient is put in the Messenger "async" queue, which the worker sends while
 * staying under the hourly quota (rate limiter "mailer").
 */
final readonly class BulkMailer
{
    public const string TRANSPORT = 'async';

    public function __construct(
        private MessageBusInterface $bus,
        private BodyRendererInterface $bodyRenderer,
    ) {
    }

    /**
     * @param iterable<string> $addresses
     *
     * @return int number of mails queued
     */
    public function queue(TemplatedEmail $email, iterable $addresses): int
    {
        // Render the Twig template now: the worker has no request, and a rendered
        // TemplatedEmail drops its context (entities) before being serialized.
        $this->bodyRenderer->render($email);

        $count = 0;
        foreach (array_unique(array_filter(array_map('trim', [...$addresses]))) as $address) {
            $copy = (clone $email)->to($address);
            $this->bus->dispatch(new SendEmailMessage($copy), [new TransportNamesStamp([self::TRANSPORT])]);
            ++$count;
        }

        return $count;
    }
}
