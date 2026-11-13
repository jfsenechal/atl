<?php

namespace AcMarche\Mercredi\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Le type Doctrine "array" (valeurs sérialisées PHP) n'existe plus dans doctrine/dbal 4.
 * Cette commande réécrit les valeurs en JSON puis change le type des colonnes.
 */
#[AsCommand(
    name: 'mercredi:migrate-array-to-json',
    description: 'Convertit les colonnes de type "array" (PHP sérialisé) en JSON : user.roles, message.destinataires',
)]
final class MigrateArrayToJsonCommand extends Command
{
    /**
     * table => colonne.
     */
    private const array COLUMNS = [
        'user' => 'roles',
        'message' => 'destinataires',
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('flush', null, InputOption::VALUE_NONE, 'Exécute les modifications (sinon dry-run)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $flush = (bool) $input->getOption('flush');

        // 1. Tout est analysé avant la moindre écriture : une valeur illisible arrête la commande.
        $updates = [];
        $errors = [];
        $rows = [];
        foreach (self::COLUMNS as $table => $column) {
            $updates[$table] = [];
            $alreadyJson = 0;
            $values = $this->connection->fetchAllKeyValue(\sprintf('SELECT id, `%s` FROM `%s`', $column, $table));
            foreach ($values as $id => $value) {
                try {
                    $json = $this->toJson($value);
                } catch (\UnexpectedValueException $exception) {
                    $errors[] = \sprintf('%s.%s #%d : %s', $table, $column, $id, $exception->getMessage());

                    continue;
                }
                if (null === $json) {
                    ++$alreadyJson;

                    continue;
                }
                $updates[$table][$id] = $json;
            }
            $rows[] = [
                $table.'.'.$column,
                \count($values),
                \count($updates[$table]),
                $alreadyJson,
                $this->isJsonColumn($table, $column) ? 'oui' : 'non',
            ];
        }

        $io->table(['Colonne', 'Lignes', 'À convertir', 'Déjà en JSON', 'Colonne JSON'], $rows);

        if ([] !== $errors) {
            $io->listing($errors);
            $io->error('Valeurs illisibles : rien n\'a été modifié.');

            return Command::FAILURE;
        }

        $queries = [];
        foreach (self::COLUMNS as $table => $column) {
            if (!$this->isJsonColumn($table, $column)) {
                $queries[] = \sprintf('ALTER TABLE `%s` CHANGE `%s` `%s` JSON NOT NULL', $table, $column, $column);
            }
        }

        if ([] === $queries && 0 === array_sum(array_map(\count(...), $updates))) {
            $io->success('Rien à faire : les colonnes sont déjà en JSON.');

            return Command::SUCCESS;
        }

        if ([] !== $queries) {
            $io->section('Requêtes');
            $io->writeln(array_map(static fn (string $sql): string => $sql.';', $queries));
        }

        if (!$flush) {
            $io->note('Dry-run : aucune modification enregistrée. Utilisez --flush pour appliquer.');

            return Command::SUCCESS;
        }

        // 2. Données : une seule transaction pour les deux tables.
        $this->connection->transactional(function (Connection $connection) use ($updates): void {
            foreach (self::COLUMNS as $table => $column) {
                foreach ($updates[$table] as $id => $json) {
                    $connection->update(\sprintf('`%s`', $table), [\sprintf('`%s`', $column) => $json], ['id' => $id]);
                }
            }
        });

        // 3. Schéma. Les DDL ne sont pas transactionnels sous MariaDB, mais la commande
        //    peut être relancée : les valeurs déjà en JSON sont ignorées.
        foreach ($queries as $sql) {
            $this->connection->executeStatement($sql);
            $io->writeln('<info>OK</info> '.$sql);
        }

        $io->success(\sprintf(
            '%d ligne(s) convertie(s), %d colonne(s) modifiée(s).',
            array_sum(array_map(\count(...), $updates)),
            \count($queries),
        ));

        return Command::SUCCESS;
    }

    /**
     * @return string|null le JSON à enregistrer, null si la valeur est déjà du JSON
     *
     * @throws \UnexpectedValueException si la valeur n'est ni un tableau sérialisé ni un tableau JSON
     */
    private function toJson(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return '[]';
        }

        if (json_validate($value)) {
            if (!\is_array(json_decode($value, true))) {
                throw new \UnexpectedValueException('JSON qui n\'est pas un tableau');
            }

            return null;
        }

        // Les anciens messages contiennent une ArrayCollection sérialisée plutôt qu'un tableau.
        $data = @unserialize($value, ['allowed_classes' => [ArrayCollection::class]]);
        if ($data instanceof ArrayCollection) {
            $data = $data->toArray();
        }
        if (!\is_array($data)) {
            throw new \UnexpectedValueException('ni PHP sérialisé ni JSON');
        }

        foreach ($data as $item) {
            if (!\is_string($item)) {
                throw new \UnexpectedValueException('contient autre chose que des chaînes');
            }
        }

        // array_values : des clés non consécutives seraient encodées en objet JSON.
        return json_encode(array_values($data), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * MySQL a un vrai type JSON ; sous MariaDB c'est un LONGTEXT avec une contrainte json_valid().
     */
    private function isJsonColumn(string $table, string $column): bool
    {
        $dataType = $this->connection->fetchOne(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column],
        );

        if ('json' === $dataType) {
            return true;
        }

        // information_schema.CHECK_CONSTRAINTS n'a de colonne TABLE_NAME que sous MariaDB
        // (absente sous MySQL) : SHOW CREATE TABLE fonctionne partout.
        $createTable = (string) $this->connection->fetchNumeric(\sprintf('SHOW CREATE TABLE `%s`', $table))[1];

        return str_contains(strtolower($createTable), 'json_valid(`'.strtolower($column).'`)');
    }
}
