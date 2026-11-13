<?php

namespace AcMarche\Mercredi\Mailer;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;

/**
 * When MAILER_RECIPIENTS is set (comma-separated), every mail is delivered only to
 * those addresses instead of the real recipients. The To/Cc headers are left as-is,
 * so the tester sees who would have received it. Empty = normal sending.
 */
//#[AsEventListener]
final readonly class SetToListener
{
    public function __construct(
        #[Autowire(env: 'csv:MAILER_RECIPIENTS')]
        private array $recipients = [],
    ) {
    }

    public function __invoke(MessageEvent $event): void
    {
        $recipients = array_filter(array_map('trim', $this->recipients));
        if ([] === $recipients) {
            return;
        }

        $event->getEnvelope()->setRecipients(Address::createArray($recipients));
    }
}
