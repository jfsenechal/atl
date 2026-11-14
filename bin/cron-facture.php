<?php

/**
 * Point d'entrée pour les « Tâches planifiées (CRON) » d'OVH mutualisé, qui
 * exécutent un fichier PHP sans arguments. Lance la commande mercredi:facture-send.
 * Les erreurs détaillées sont dans les logs CRON d'OVH et résumées dans var/log/cron.log.
 */

require __DIR__.'/cron-bootstrap.php';

run_cron('cron-facture', ['command' => 'mercredi:facture-send']);
