<?php

namespace App\Tests\Mailer;

use AcMarche\Mercredi\Mailer\Events\MailerEventsSubscriber;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MailerEventsSubscriberTest extends KernelTestCase
{
    /**
     * Mail failures must not go to the "app" channel: in prod that channel is mailed to the
     * webmaster, so a broken SMTP server would make each failure send a new failing mail.
     */
    public function testLogsOnTheMailChannel(): void
    {
        $subscriber = self::getContainer()->get(MailerEventsSubscriber::class);
        $logger = (new \ReflectionProperty($subscriber, 'mercredilogger'))->getValue($subscriber);

        self::assertInstanceOf(Logger::class, $logger);
        self::assertSame('mail', $logger->getName());
    }
}
