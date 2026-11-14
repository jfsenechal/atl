<?php

namespace AcMarche\Mercredi\Command;

use AcMarche\Mercredi\Facture\Handler\FactureCronHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(
    name: 'mercredi:facture-send',
    description: 'Traite les envois de factures planifiés (FactureCron) et envoie les mails'
)]
class SendFactureCommand extends Command
{
    public function __construct(
        private readonly FactureCronHandler $factureCronHandler,
        private readonly LockFactory $lockFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('max', null, InputOption::VALUE_REQUIRED, 'Nombre maximum de factures envoyées par exécution', 40);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Une exécution peut durer jusqu'à 60 minutes chez l'hébergeur
        $lock = $this->lockFactory->createLock('mercredi-facture-send', 3600);
        if (!$lock->acquire()) {
            $io->warning('Un envoi de factures est déjà en cours.');

            return Command::SUCCESS;
        }

        $failed = false;
        try {
            $result = $this->factureCronHandler->execute((int) $input->getOption('max'));
        } catch (\Throwable $exception) {
            $failed = true;
            $result = [['error' => $exception::class.': '.$exception->getMessage()]];
            $io->error(\sprintf('%s: %s at %s:%d', $exception::class, $exception->getMessage(), $exception->getFile(), $exception->getLine()));
            if ($output->isVerbose()) {
                $io->writeln($exception->getTraceAsString());
            }
        } finally {
            $lock->release();
        }

        foreach ($result as $line) {
            $io->writeln($line['message'] ?? $line['error']);
        }

        if ([] !== $result) {
            try {
                $this->factureCronHandler->sendResult($result);
            } catch (\Throwable $exception) {
                $io->error('Rapport non envoyé: '.$exception->getMessage());
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
