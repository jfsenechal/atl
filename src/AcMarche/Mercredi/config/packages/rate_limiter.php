<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('framework', [
        'rate_limiter' => [
            'anonymous_api' => [
                'policy' => 'fixed_window',
                'limit' => 30,
                'interval' => '60 minutes',
            ],
            // OVH shared hosting accepts a limited number of mails per hour: shared by the
            // Messenger worker (async transport) and the invoice cron, which sends over SMTP
            'mailer' => [
                'policy' => 'fixed_window',
                'limit' => '%env(int:MAILER_HOURLY_LIMIT)%',
                'interval' => '60 minutes',
            ],
        ],
    ]);
};
