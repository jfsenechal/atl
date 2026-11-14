<?php

/**
 * Test d'envoi de mail dans les conditions réelles d'une « Tâche planifiée (CRON) »
 * d'OVH mutualisé (même binaire PHP, même utilisateur). Les tests lancés en SSH ne
 * sont pas représentatifs : OVH y interdit l'envoi (« not allowed to submit mail »).
 *
 * Envoie un mail de test de MERCREDI_FROM vers MERCREDI_FROM avec le MAILER_DSN
 * configuré. Créer une tâche temporaire pointant vers ce fichier, attendre une
 * exécution, lire var/log/cron.log et var/log/cron-cron-mailtest.last.log, puis
 * supprimer la tâche.
 */

require __DIR__.'/cron-bootstrap.php';

run_cron('cron-mailtest', static fn (): array => [
    'command' => 'mailer:test',
    'to' => $_SERVER['MERCREDI_FROM'],
    '--from' => $_SERVER['MERCREDI_FROM'],
    '--subject' => 'Test mail CRON '.date('Y-m-d H:i:s'),
    '--body' => sprintf('Envoyé par bin/cron-mailtest.php (PHP %s, %s).', \PHP_VERSION, \PHP_SAPI),
]);
