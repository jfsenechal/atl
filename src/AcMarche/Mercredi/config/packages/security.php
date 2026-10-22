<?php

use AcMarche\Mercredi\Entity\Security\User;
use AcMarche\Mercredi\Security\Authenticator\MercrediAuthenticator;
use AcMarche\Mercredi\Security\Authenticator\MercrediLdapAuthenticator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Ldap\LdapInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $authenticators = [MercrediAuthenticator::class];

    $mainFirewall = [
        'lazy' => true,
        'switch_user' => true,
        'form_login' => [
            'login_path' => 'app_login',
            'remember_me' => true,
            'enable_csrf' => true,
        ],
        'logout' => [
            'path' => 'app_logout',
        ],
    ];

    if (interface_exists(LdapInterface::class)) {
        $authenticators[] = MercrediLdapAuthenticator::class;
        $mainFirewall['form_login_ldap'] = [
            'service' => Ldap::class,
            'check_path' => 'app_login',
        ];
    }

    $mainFirewall += [
        'custom_authenticators' => $authenticators,
        'provider' => 'mercredi_user_provider',
        'entry_point' => MercrediAuthenticator::class,
        'login_throttling' => [
            'max_attempts' => 6,
            'interval' => '15 minutes',
        ],
        'remember_me' => [
            'secret' => '%kernel.secret%',
            'lifetime' => 604800,
            'path' => '/',
            'always_remember_me' => true,
        ],
    ];

    $containerConfigurator->extension('security', [
        'providers' => [
            'mercredi_user_provider' => [
                'entity' => [
                    'class' => User::class,
                    'manager_name' => 'default',
                ],
            ],
        ],
        'firewalls' => [
            'dev' => [
                'pattern' => '^/(_(profiler|wdt)|css|images|js)/',
                'security' => false,
            ],
            'main' => $mainFirewall,
        ],
        'role_hierarchy' => [
            'ROLE_MERCREDI_ADMIN' => ['ROLE_MERCREDI', 'ROLE_ALLOWED_TO_SWITCH'],
            'ROLE_MERCREDI_PARENT' => ['ROLE_MERCREDI'],
            'ROLE_MERCREDI_ECOLE' => ['ROLE_MERCREDI'],
            'ROLE_MERCREDI_ANIMATEUR' => ['ROLE_MERCREDI'],
        ],
    ]);
};
