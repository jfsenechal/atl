<?php

namespace App\Tests\Mailer;

use AcMarche\Mercredi\Mailer\Debug\MailDebugger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MailDebuggerTest extends KernelTestCase
{
    private MailDebugger $debugger;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->debugger = self::getContainer()->get(MailDebugger::class);
    }

    public function testReportsThatMailsGoThroughTheAsyncQueue(): void
    {
        self::assertSame(['async'], $this->debugger->mailRouting());
        self::assertStringNotContainsString('secret', $this->debugger->maskedDsn());
    }

    public function testMasksThePasswordOfTheDsn(): void
    {
        $debugger = new \ReflectionClass(MailDebugger::class);
        $instance = $debugger->newInstanceWithoutConstructor();
        $debugger->getProperty('mailerDsn')->setValue($instance, 'smtp://user%40x.be:secret@ssl0.ovh.net:465');

        self::assertSame('smtp://user%40x.be:****@ssl0.ovh.net:465', $instance->maskedDsn());
    }

    public function testSendsDirectlyAndReportsSuccess(): void
    {
        $email = $this->debugger->createTestEmail('factures@example.com', 'parent@example.com', 'Test');

        $result = $this->debugger->sendDirect($email);

        self::assertTrue($result['success']);
        self::assertNull($result['error']);
    }

    public function testQueueNeverThrows(): void
    {
        // Without a database the error is returned so the page can show it
        $queue = $this->debugger->queue('async');

        self::assertArrayHasKey('error', $queue);
        self::assertIsArray($queue['messages']);
        if (null === $queue['error']) {
            self::assertIsInt($queue['count']);
        }
    }
}
