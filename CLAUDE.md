# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

The **Hotton** deployment of *Mercredi*, an app for managing Wednesday/holiday childcare days (journées pédagogiques) and holiday camps ("plaines": carnaval, Pâques…), originally built by the Ville de Marche-en-Famenne. It runs on Symfony 7.4 (LTS) and PHP 8.5+, uses Doctrine ORM 3 (native lazy objects enabled, so PHP ≥ 8.4 is mandatory) with MariaDB 11.8 (the Postgres service in `compose.yaml` is unused Flex boilerplate), and serves front-end assets through AssetMapper/importmap (Stimulus, Turbo, tom-select). There is no Node build step.

## Two codebases in one tree

- **Host app** (`/var/www/hotton`): a thin Symfony skeleton. It is not a git repo. `src/App/Kernel.php` is the kernel; `App\` maps to `src/App`. `src/Controller`, `src/Entity`, `src/Repository` and `migrations/` are empty.
- **Mercredi bundle** (`src/AcMarche/Mercredi`): almost all of the application code. It is its own git repository (`https://github.com/acmarche/mercredi.git`), shared with other communes (Marche…). Namespace `AcMarche\Mercredi\` → `src/AcMarche/Mercredi/src`. Commit bundle changes from inside that directory.
- `old/` is a leftover Symfony 6.4 copy of the app. Ignore it unless you're asked to migrate something from it.

Changes to the bundle affect every commune that uses it. Hotton-only behavior goes through the commune-specific implementations described below, not through edits to shared code paths.

## Per-commune customization (key architectural point)

The bundle defines interfaces in `src/AcMarche/Mercredi/src/Contrat/` (presence calculation, tarification forms, plaine handling/calculation, invoice rendering/PDFs, payment communication, attestations). Each commune has its own implementations (`*Hotton*` vs `*Marche*` classes, e.g. `Presence/Calculator/PrenceHottonCalculator`, `Facture/Render/FactureRenderHotton`, `Plaine/Calculator/PlaineHottonCalculator`).

The host's `config/services.yaml` picks the implementation by aliasing each interface. The Marche aliases are kept there, commented out, for reference. To change Hotton-specific pricing, invoices or attestations, edit the `*Hotton` class or the alias. Don't edit the interface consumers.

## Bundle wiring

`AcMarcheMercrediBundle` (an `AbstractBundle`):
- loads `config/services.php`, which autowires everything in `src/` except `Entity`, binds `MERCREDI_*` env vars to `Parameter\Option` constants, and registers LDAP services only when `symfony/ldap` is installed
- prepends its own package config from `config/packages/*.php`: Doctrine mappings for `src/Entity`, security (firewall, form login, optional LDAP, login throttling, remember-me), messenger, vich_uploader, liip_imagine, twig and rate_limiter. Security and Doctrine mapping live in the bundle, not in the host's `config/packages`. Write these files as `$containerConfigurator->extension('name', [...])` arrays: the fluent `Symfony\Config\*Config` builders are deprecated in 7.4.
- auto-tags `PresenceConstraintInterface` implementations (`mercredi.presence_constraint`), which are collected by `DependencyInjection/PresenceConstraintPass`

Routes (`config/routes.yaml`) load attribute controllers from the bundle by audience:
`Controller/Front` (`/`), `Admin` (`/admin`), `Parent` (`/parent`), `Ecole` (`/ecole`), `Animateur` (`/animateur`); there is also `Controller/Api`. Roles are in `Security/Role/MercrediSecurityRole.php` (`ROLE_MERCREDI_ADMIN|PARENT|ECOLE|ANIMATEUR`). Templates live in `src/AcMarche/Mercredi/templates/` and are organized by the same audiences.

The domain is organized by folders under the bundle's `src/` (Enfant, Tuteur, Presence, Plaine, Jour, Facture, Paiement/Reduction, Scolaire, Sante, Animateur, Ecole…). Each folder usually holds its own Repository, Form, Handler and similar classes. The vocabulary is French: *tuteur* = parent/guardian, *enfant* = child, *présence* = attendance, *facture* = invoice.

## Commands

```bash
php bin/console cache:clear
sh clean.sh                              # wipe var/cache/{prod,dev,rss} and var/log
sh git.sh                                # git pull the bundle, then clean.sh (deployment update)
php bin/console doctrine:schema:update --dump-sql   # Doctrine; entities are in the bundle
php bin/console debug:router
php bin/phpunit                          # tests/ is currently empty apart from bootstrap
php bin/phpunit --filter SomeTest
```

Bundle console commands are in `src/AcMarche/Mercredi/src/Command/`: invoice PDF generation/sending, reminders, tutor archiving, health check, mail test.

PDFs are rendered with wkhtmltopdf via KnpSnappy (`WKHTMLTOPDF_PATH`; there's a binary in `bin/`). App behavior is driven by `MERCREDI_*` env vars in `.env.local`: feature toggles (register, accueil, paiement, plaine), prices and deadlines.

Production runs on OVH shared hosting, which must be set to PHP 8.4 (there's no `.ovhconfig` in this tree).
