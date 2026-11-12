<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routingConfigurator): void {
    $routingConfigurator
        ->import('../../src/Controller/Front/', 'attribute');

    $routingConfigurator
        ->import('../../src/Controller/Api/', 'attribute');

    $routingConfigurator
        ->import('../../src/Controller/Admin/', 'attribute')
        ->prefix('/admin');

    $routingConfigurator
        ->import('../../src/Controller/Parent/', 'attribute')
        ->prefix('/parent');

    $routingConfigurator
        ->import('../../src/Controller/Ecole/', 'attribute')
        ->prefix('/ecole');

    $routingConfigurator
        ->import('../../src/Controller/Animateur/', 'attribute')
        ->prefix('/animateur');
};
