<?php

namespace AcMarche\Mercredi\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mercredi:remove-plaine-schema',
    description: 'Supprime de la base les tables et colonnes des plaines (plaine, plaine_groupe, jour.plaine_id, facture.plaine_id/plaine_nom)',
)]
class RemovePlaineSchemaCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'flush',
                null,
                InputOption::VALUE_NONE,
                'Exécute les requêtes (sinon dry-run)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $flush = (bool)$input->getOption('flush');

        if (!$this->checkNoPlaineData()) {
            return Command::FAILURE;
        }

        $queries = $this->buildQueries();

        if ([] === $queries) {
            $this->io->success('Rien à faire : le schéma ne contient plus de plaines.');

            return Command::SUCCESS;
        }

        $this->io->section('Requêtes');
        foreach ($queries as $sql) {
            $this->io->writeln($sql.';');
        }

        if (!$flush) {
            $this->io->note('Dry-run : aucune modification enregistrée. Utilisez --flush pour appliquer.');

            return Command::SUCCESS;
        }

        // Les DDL ne sont pas transactionnels sous MariaDB : chaque requête est
        // vérifiée avant d'être générée, la commande peut donc être relancée après une erreur.
        foreach ($queries as $sql) {
            $this->connection->executeStatement($sql);
            $this->io->writeln('<info>OK</info> '.$sql);
        }

        $this->io->success(sprintf('%d requête(s) exécutée(s).', \count($queries)));

        return Command::SUCCESS;
    }

    /**
     * Supprimer jour.plaine_id transformerait les journées de plaine en journées ordinaires :
     * on refuse de continuer tant qu'il reste des données liées aux plaines.
     */
    private function checkNoPlaineData(): bool
    {
        $checks = [];
        if ($this->tableExists('plaine')) {
            $checks['plaines'] = 'SELECT COUNT(*) FROM plaine';
        }
        if ($this->tableExists('plaine_groupe')) {
            $checks['groupes de plaine'] = 'SELECT COUNT(*) FROM plaine_groupe';
        }
        if ($this->columnExists('jour', 'plaine_id')) {
            $checks['journées de plaine'] = 'SELECT COUNT(*) FROM jour WHERE plaine_id IS NOT NULL';
        }
        if ($this->columnExists('facture', 'plaine_id')) {
            $checks['factures de plaine'] = 'SELECT COUNT(*) FROM facture WHERE plaine_id IS NOT NULL';
        }
        if ($this->columnExists('facture', 'plaine_nom')) {
            $checks['factures avec plaine_nom'] = 'SELECT COUNT(*) FROM facture WHERE plaine_nom IS NOT NULL';
        }
        $checks['présences facturées de type plaine'] = "SELECT COUNT(*) FROM facture_presence WHERE object_type = 'plaine'";

        $rows = [];
        $hasData = false;
        foreach ($checks as $label => $sql) {
            $count = (int)$this->connection->fetchOne($sql);
            $rows[] = [$label, $count];
            if ($count > 0) {
                $hasData = true;
            }
        }

        $this->io->table(['Données', 'Nombre'], $rows);

        if ($hasData) {
            $this->io->error('Il reste des données de plaine : rien n\'a été modifié. Supprimez-les ou migrez-les avant de relancer.');

            return false;
        }

        return true;
    }

    /**
     * @return string[]
     */
    private function buildQueries(): array
    {
        $queries = [];

        // 1. Clés étrangères pointant vers plaine (jour, facture, plaine_groupe) et celles de plaine_groupe
        $foreignKeys = $this->connection->fetchAllAssociative(
            "SELECT DISTINCT TABLE_NAME, CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
             AND (REFERENCED_TABLE_NAME = 'plaine' OR TABLE_NAME = 'plaine_groupe')"
        );
        foreach ($foreignKeys as $foreignKey) {
            $queries[] = sprintf(
                'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                $foreignKey['TABLE_NAME'],
                $foreignKey['CONSTRAINT_NAME']
            );
        }

        // 2. Tables des plaines
        foreach (['plaine_groupe', 'plaine'] as $table) {
            if ($this->tableExists($table)) {
                $queries[] = sprintf('DROP TABLE `%s`', $table);
            }
        }

        // 3. Index contenant plaine_id : à supprimer avant la colonne, sinon MariaDB
        //    réduirait l'unique (date_jour, pedagogique, plaine_id) à (date_jour, pedagogique),
        //    ce qui échoue car plusieurs écoles ont une journée pédagogique à la même date.
        foreach (['jour', 'facture'] as $table) {
            foreach ($this->indexesOnColumn($table, 'plaine_id') as $index) {
                $queries[] = sprintf('DROP INDEX `%s` ON `%s`', $index, $table);
            }
        }

        // 4. Colonnes
        foreach (['jour' => ['plaine_id'], 'facture' => ['plaine_id', 'plaine_nom']] as $table => $columns) {
            $drops = [];
            foreach ($columns as $column) {
                if ($this->columnExists($table, $column)) {
                    $drops[] = sprintf('DROP `%s`', $column);
                }
            }
            if ([] !== $drops) {
                $queries[] = sprintf('ALTER TABLE `%s` %s', $table, implode(', ', $drops));
            }
        }

        return $queries;
    }

    private function tableExists(string $table): bool
    {
        return (bool)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        return (bool)$this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }

    /**
     * @return string[]
     */
    private function indexesOnColumn(string $table, string $column): array
    {
        return $this->connection->fetchFirstColumn(
            "SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND INDEX_NAME <> 'PRIMARY'",
            [$table, $column]
        );
    }
}
