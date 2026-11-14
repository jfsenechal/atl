<?php

namespace AcMarche\Mercredi\Command;

use AcMarche\Mercredi\Mailer\BulkMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

#[AsCommand(
    name: 'mercredi:test-mail',
    description: 'Envoie un mail de test, directement ou via la file "async" (--queue) traitée par bin/cron-messenger.php'
)]
class MailTestCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly BulkMailer $bulkMailer,
        #[Autowire('%env(MERCREDI_FROM)%')]
        private readonly string $from,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Destinataire')
            ->addOption('queue', null, InputOption::VALUE_NONE, 'Mettre le mail dans la file "async" (table messenger_messages) au lieu de l\'envoyer')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'Nombre de mails à mettre en file (avec --queue)', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = $input->getArgument('to');
        $queue = $input->getOption('queue');
        $count = max(1, (int) $input->getOption('count'));

        if (!$queue) {
            try {
                $this->mailer->send($this->createEmail($to, 'direct'));
            } catch (TransportExceptionInterface $e) {
                $io->error('Erreur lors de l\'envoi: '.$e->getMessage());

                return Command::FAILURE;
            }
            $io->success(\sprintf('Mail envoyé à %s.', $to));

            return Command::SUCCESS;
        }

        for ($i = 1; $i <= $count; ++$i) {
            $this->bulkMailer->queue($this->createEmail($to, \sprintf('file async %d/%d', $i, $count)), [$to]);
        }
        $io->success(\sprintf('%d mail(s) pour %s mis dans la file "async". Ils partiront au prochain passage de bin/cron-messenger.php.', $count, $to));

        return Command::SUCCESS;
    }

    private function createEmail(string $to, string $mode): TemplatedEmail
    {
        return (new TemplatedEmail())
            ->from($this->from)
            ->to($to)
            ->subject(\sprintf('Test application mercredi (%s) %s', $mode, date('Y-m-d H:i:s')))
            ->text(\sprintf("Mail de test (%s), créé le %s.\n", $mode, date('Y-m-d H:i:s')));
    }
}
