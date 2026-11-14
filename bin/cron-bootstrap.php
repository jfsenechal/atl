<?php

/**
 * Lanceur commun aux scripts bin/cron-*.php (« Tâches planifiées (CRON) » d'OVH
 * mutualisé, qui exécutent un fichier PHP sans arguments).
 *
 * Rend les erreurs lisibles dans les logs CRON d'OVH (hébergement → Plus → Logs) :
 * - en-tête : date, PHP, environnement, limites ;
 * - commande lancée en mode très verbeux (-vv, trace des exceptions en -vvv) ;
 * - toute exception ou erreur fatale PHP est affichée avec fichier, ligne et trace ;
 * - code de sortie, durée et mémoire en fin d'exécution.
 * Un résumé d'une ligne par exécution est aussi ajouté à var/log/cron.log.
 */

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Dotenv\Dotenv;

/**
 * @param array<string, mixed> $arguments arguments of the console command, 'command' included
 */
function run_cron(string $name, array $arguments): never
{
    $projectDir = dirname(__DIR__);
    $start = microtime(true);
    $exitCode = 1;
    $summary = 'not started';

    error_reporting(\E_ALL);
    ini_set('display_errors', 'stderr');
    ini_set('log_errors', '0');
    // OVH lance le script depuis la racine de l'hébergement : chemins relatifs au projet
    chdir($projectDir);

    $line = static function (string $text): void {
        fwrite(\STDOUT, '['.date('Y-m-d H:i:s').'] '.$text.\PHP_EOL);
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

    $finish = static function () use ($name, $projectDir, $start, &$exitCode, &$summary, $line): void {
        $duration = round(microtime(true) - $start, 1);
        $memory = round(memory_get_peak_usage(true) / 1048576, 1);
        $line(sprintf('END %s exit=%d duration=%ss peak_memory=%sMB', $name, $exitCode, $duration, $memory));
        @file_put_contents(
            $projectDir.'/var/log/cron.log',
            sprintf("[%s] %s exit=%d duration=%ss memory=%sMB %s\n", date('Y-m-d H:i:s'), $name, $exitCode, $duration, $memory, $summary),
            \FILE_APPEND | \LOCK_EX,
        );
    };

    // Erreurs fatales (mémoire, temps d'exécution, classe manquante...) : pas d'exception à attraper
    register_shutdown_function(static function () use (&$exitCode, &$summary, $finish, $line): void {
        $error = error_get_last();
        if (null === $error || !\in_array($error['type'], [\E_ERROR, \E_PARSE, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR], true)) {
            return;
        }
        $exitCode = 255;
        $summary = sprintf('FATAL %s at %s:%d', $error['message'], $error['file'], $error['line']);
        $line('PHP FATAL ERROR: '.$error['message']);
        $line('  at '.$error['file'].':'.$error['line']);
        $finish();
    });

    $line(sprintf(
        'START %s | PHP %s (%s) | memory_limit=%s | max_execution_time=%s | cwd=%s',
        $name,
        \PHP_VERSION,
        \PHP_SAPI,
        ini_get('memory_limit'),
        ini_get('max_execution_time'),
        getcwd(),
    ));

    try {
        require $projectDir.'/vendor/autoload.php';
        (new Dotenv())->bootEnv($projectDir.'/.env');
        $line(sprintf('APP_ENV=%s APP_DEBUG=%s command=%s', $_SERVER['APP_ENV'], $_SERVER['APP_DEBUG'] ? '1' : '0', json_encode($arguments)));

        $kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
        $application = new Application($kernel);
        $application->setAutoExit(false);
        // Exceptions handled below so they are always printed in full
        $application->setCatchExceptions(false);

        $output = new ConsoleOutput(OutputInterface::VERBOSITY_VERY_VERBOSE, false);
        $exitCode = $application->run(new ArrayInput($arguments), $output);
        $summary = 0 === $exitCode ? 'ok' : 'command returned an error, see the cron output';
    } catch (\Throwable $e) {
        $exitCode = 1;
        $summary = sprintf('%s: %s at %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine());
        $line('UNCAUGHT EXCEPTION');
        fwrite(\STDOUT, $describe($e));
    }

    $finish();
    exit($exitCode);
}
