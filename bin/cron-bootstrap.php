<?php

/**
 * Lanceur commun aux scripts bin/cron-*.php (« Tâches planifiées (CRON) » d'OVH
 * mutualisé, qui exécutent un fichier PHP sans arguments).
 *
 * Attention : OVH peut lancer le script avec le binaire PHP CGI et non CLI. Les
 * constantes STDIN/STDOUT/STDERR n'y existent pas, et max_execution_time n'y est
 * pas illimité. Ce lanceur n'utilise donc que des flux ouverts explicitement.
 *
 * Traces écrites :
 * - var/log/cron.log : une ligne START dès le lancement (avant tout chargement),
 *   puis une ligne de fin (code de sortie, durée, mémoire, résumé). Un START sans
 *   ligne de fin = processus tué (temps, mémoire) avant la fin ;
 * - var/log/cron-<nom>.last.log : sortie complète (-vv) de la dernière exécution,
 *   exceptions et erreurs fatales PHP comprises ;
 * - la même sortie est aussi affichée dans les logs CRON d'OVH (hébergement → Plus → Logs).
 */

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Dotenv\Dotenv;

/**
 * @param array<string, mixed>|\Closure(): array<string, mixed> $arguments arguments of the console command,
 *                                                               'command' included; a closure is called once
 *                                                               the .env files are loaded (to read env vars)
 */
function run_cron(string $name, array|\Closure $arguments): never
{
    $projectDir = dirname(__DIR__);
    $logDir = $projectDir.'/var/log';
    $start = microtime(true);
    $exitCode = 1;
    $summary = 'not finished';
    $finished = false;

    error_reporting(\E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '0');
    @set_time_limit(0);
    // OVH lance le script depuis la racine de l'hébergement : chemins relatifs au projet
    chdir($projectDir);
    @mkdir($logDir, 0775, true);

    $summaryLog = static function (string $text) use ($logDir): void {
        @file_put_contents($logDir.'/cron.log', '['.date('Y-m-d H:i:s').'] '.$text."\n", \FILE_APPEND | \LOCK_EX);
    };

    // Premier écrit : prouve que le script a démarré, quel que soit l'environnement
    $summaryLog(sprintf(
        '%s START sapi=%s php=%s memory_limit=%s max_execution_time=%s',
        $name,
        \PHP_SAPI,
        \PHP_VERSION,
        ini_get('memory_limit'),
        ini_get('max_execution_time'),
    ));

    // Sortie complète de cette exécution (fichier) + sortie standard (logs OVH)
    $stdout = \defined('STDOUT') ? \STDOUT : fopen('php://output', 'w');
    $file = @fopen($logDir.'/cron-'.$name.'.last.log', 'w');
    $write = static function (string $text) use ($stdout, $file): void {
        if (false !== $stdout) {
            @fwrite($stdout, $text);
        }
        if (false !== $file) {
            @fwrite($file, $text);
        }
    };
    $line = static function (string $text) use ($write): void {
        $write('['.date('Y-m-d H:i:s').'] '.$text.\PHP_EOL);
    };

    $describe = static function (\Throwable $e): string {
        $text = '';
        $prefix = '';
        do {
            $text .= $prefix.$e::class.': '.$e->getMessage().\PHP_EOL
                .'  at '.$e->getFile().':'.$e->getLine().\PHP_EOL
                .$e->getTraceAsString().\PHP_EOL;
            $prefix = 'Caused by: ';
        } while ($e = $e->getPrevious());

        return $text;
    };

    $finish = static function () use ($name, $start, &$exitCode, &$summary, &$finished, $line, $summaryLog): void {
        if ($finished) {
            return;
        }
        $finished = true;
        $duration = round(microtime(true) - $start, 1);
        $memory = round(memory_get_peak_usage(true) / 1048576, 1);
        $line(sprintf('END %s exit=%d duration=%ss peak_memory=%sMB', $name, $exitCode, $duration, $memory));
        $summaryLog(sprintf('%s exit=%d duration=%ss memory=%sMB %s', $name, $exitCode, $duration, $memory, $summary));
    };

    // Erreurs fatales (mémoire, temps d'exécution, classe manquante...) : pas d'exception à attraper
    register_shutdown_function(static function () use (&$exitCode, &$summary, $finish, $line): void {
        $error = error_get_last();
        if (null !== $error && \in_array($error['type'], [\E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR], true)) {
            $exitCode = 255;
            $summary = sprintf('FATAL %s at %s:%d', $error['message'], $error['file'], $error['line']);
            $line('PHP FATAL ERROR: '.$error['message']);
            $line('  at '.$error['file'].':'.$error['line']);
        }
        // Sortie anormale (exit() dans le code, erreur fatale...) : on trace quand même la fin
        $finish();
    });

    $line(sprintf('START %s | PHP %s (%s) | memory_limit=%s | max_execution_time=%s | cwd=%s', $name, \PHP_VERSION, \PHP_SAPI, ini_get('memory_limit'), ini_get('max_execution_time'), getcwd()));

    try {
        require $projectDir.'/vendor/autoload.php';
        (new Dotenv())->bootEnv($projectDir.'/.env');
        if ($arguments instanceof \Closure) {
            $arguments = $arguments();
        }
        $line(sprintf('APP_ENV=%s APP_DEBUG=%s command=%s', $_SERVER['APP_ENV'], $_SERVER['APP_DEBUG'] ? '1' : '0', json_encode($arguments)));

        $kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
        $application = new Application($kernel);
        $application->setAutoExit(false);
        // Exceptions handled below so they are always printed in full
        $application->setCatchExceptions(false);

        // Écrit directement dans le fichier (conservé même si le processus est tué),
        // recopié ensuite sur la sortie standard pour les logs OVH
        $stream = false !== $file ? $file : fopen('php://memory', 'w+');
        $offset = ftell($stream);
        $output = new StreamOutput($stream, OutputInterface::VERBOSITY_VERY_VERBOSE, false);
        try {
            $exitCode = $application->run(new ArrayInput($arguments), $output);
        } finally {
            if (false !== $stdout) {
                fflush($stream);
                $copy = fopen(stream_get_meta_data($stream)['uri'], 'r');
                if (false !== $copy) {
                    fseek($copy, $offset);
                    stream_copy_to_stream($copy, $stdout);
                    fclose($copy);
                }
            }
        }
        $summary = 0 === $exitCode ? 'ok' : 'command returned an error, see var/log/cron-'.$name.'.last.log';
    } catch (\Throwable $e) {
        $exitCode = 1;
        $summary = sprintf('%s: %s at %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine());
        $line('UNCAUGHT EXCEPTION');
        $write($describe($e));
    }

    $finish();
    exit($exitCode);
}
