<?php

/**
 * Point d'entrée pour les « Tâches planifiées (CRON) » d'OVH mutualisé, qui
 * exécutent un fichier PHP sans arguments. Traite la file Messenger « async »
 * (envois de masse : messages aux parents) pendant 55 minutes, au rythme permis
 * par le limiteur « mailer » (MAILER_HOURLY_LIMIT mails par heure).
 * Les erreurs détaillées sont dans les logs CRON d'OVH et résumées dans var/log/cron.log.
 */

require __DIR__.'/cron-bootstrap.php';

run_cron('cron-messenger', [
    'command' => 'messenger:consume',
    'receivers' => ['async'],
    // Lancé chaque heure : s'arrête avant le passage suivant
    '--time-limit' => 3300,
    '--memory-limit' => '128M',
]);
