<?php

namespace App\Tests\Command;

use AcMarche\Mercredi\Facture\Handler\FactureCronHandler;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

final class SendFactureCommandTest extends KernelTestCase
{
    public function testRunsHandlerAndSendsReport(): void
    {
        $result = [['message' => 'count 3']];
        $handler = $this->createMock(FactureCronHandler::class);
        $handler->expects(self::once())->method('execute')->with(25)->willReturn($result);
        $handler->expects(self::once())->method('sendResult')->with($result);

        $tester = $this->createTester($handler);

        self::assertSame(Command::SUCCESS, $tester->execute(['--max' => 25]));
        self::assertStringContainsString('count 3', $tester->getDisplay());
    }

    public function testNoReportWhenNothingToDo(): void
    {
        $handler = $this->createMock(FactureCronHandler::class);
        $handler->expects(self::once())->method('execute')->with(40)->willReturn([]);
        $handler->expects(self::never())->method('sendResult');

        self::assertSame(Command::SUCCESS, $this->createTester($handler)->execute([]));
    }

    public function testFailureIsReported(): void
    {
        $handler = $this->createMock(FactureCronHandler::class);
        $handler->method('execute')->willThrowException(new \RuntimeException('boom'));
        $handler->expects(self::once())->method('sendResult')->with([['error' => 'RuntimeException: boom']]);

        $tester = $this->createTester($handler);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('boom', $tester->getDisplay());
    }

    public function testSkipsWhenAlreadyRunning(): void
    {
        $handler = $this->createMock(FactureCronHandler::class);
        $handler->expects(self::never())->method('execute');
        $handler->expects(self::never())->method('sendResult');

        $tester = $this->createTester($handler);

        // Même verrou que la commande, pris par un « autre processus »
        $lock = new LockFactory(new FlockStore())->createLock('mercredi-facture-send');
        self::assertTrue($lock->acquire());

        try {
            self::assertSame(Command::SUCCESS, $tester->execute([]));
            self::assertStringContainsString('déjà en cours', $tester->getDisplay());
        } finally {
            $lock->release();
        }
    }

    private function createTester(FactureCronHandler $handler): CommandTester
    {
        self::bootKernel();
        self::getContainer()->set(FactureCronHandler::class, $handler);

        return new CommandTester(new Application(self::$kernel)->find('mercredi:facture-send'));
    }
}
