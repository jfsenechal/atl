<?php

/**
 * Point d'entrée pour les « Tâches planifiées (CRON) » d'OVH mutualisé, qui
 * exécutent un fichier PHP sans arguments. Traite la file Messenger « async »
 * (mails de l'application) pendant 55 minutes, au rythme permis par le
 * limiteur « mailer » (MAILER_HOURLY_LIMIT mails par heure).
 */

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);

$application = new Application($kernel);
$application->setAutoExit(false);

exit($application->run(new ArrayInput([
    'command' => 'messenger:consume',
    'receivers' => ['async'],
    // Lancé chaque heure : s'arrête avant le passage suivant
    '--time-limit' => 3300,
    '--memory-limit' => '128M',
]), new ConsoleOutput()));
