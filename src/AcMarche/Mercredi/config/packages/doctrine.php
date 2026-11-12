<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('doctrine', [
        /*'dbal' => [
            'connections' => [
                'grh' => [
                    'url' => '%env(DATABASE_GRH_URL)%',
                    'charset' => 'utf8mb4',
                ],
            ],
        ],*/
        'orm' => [
            'entity_managers' => [
                'default' => [
                    'connection' => 'default',
                    'mappings' => [
                        'AcMarcheGrh' => [
                            'is_bundle' => false,
                            'type' => 'attribute',
                            'dir' => '%kernel.project_dir%/src/AcMarche/Mercredi/src/Entity',
                            'prefix' => 'AcMarche\Mercredi',
                            'alias' => 'AcMarcheMercredi',
                        ],
                    ],
                ],
            ],
        ],
    ]);
};
