# Production deployment

Commands to run on the production server, from the project root, in this order.

## 0. Before starting

Back up the database. Steps 2 and 3 rewrite data and drop tables; they cannot be undone without a backup.

```bash
mariadb-dump -u <user> -p <database> > backup_$(date +%F_%H%M).sql
```

Stop any older version of the application that uses the same database: once
step 2 has run, it can no longer read `user.roles` or `message.destinataires`.

## 1. Code and PHP dependencies

```bash
git pull
APP_ENV=prod composer install --no-dev --optimize-autoloader
```

`composer install` also runs the auto-scripts: `cache:clear`,
`assets:install`, `importmap:install` and the CKEditor 5 asset install.

## 2. Convert the "array" columns to JSON (once)

Converts `user.roles` and `message.destinataires` from PHP-serialized values to
JSON, then changes the column type. Safe to re-run.

```bash
# dry-run: shows the rows to convert and the queries, changes nothing
APP_ENV=prod bin/console mercredi:migrate-array-to-json

# apply
APP_ENV=prod bin/console mercredi:migrate-array-to-json --flush
```

If the dry-run lists unreadable values, stop: nothing is modified until they are fixed.

## 3. Remove the "plaine" schema (once)

Drops the `plaine` and `plaine_groupe` tables and the `plaine_id` / `plaine_nom`
columns. The command refuses to run if any plaine data is left.

```bash
# dry-run
APP_ENV=prod bin/console mercredi:remove-plaine-schema

# apply
APP_ENV=prod bin/console mercredi:remove-plaine-schema --flush
```

## 4. Doctrine migrations

```bash
APP_ENV=prod bin/console doctrine:migrations:migrate --no-interaction
```

## 5. Check the schema

```bash
APP_ENV=prod bin/console doctrine:schema:validate
```

Expected result: mapping correct and database in sync. If differences remain,
list them with `bin/console doctrine:schema:update --dump-sql` (do not use `--force`).

## 6. Assets

The application uses AssetMapper (no Node.js, no build step). `assets/vendor/`
and `public/assets/` are not in git, so they are rebuilt on the server.

```bash
# download the importmap packages (Bootstrap, Font Awesome, Stimulus, ...) into assets/vendor/
APP_ENV=prod bin/console importmap:install

# copy the bundles' public files into public/bundles/
APP_ENV=prod bin/console assets:install public

# compile all versioned assets into public/assets/
APP_ENV=prod bin/console asset-map:compile
```

Run `asset-map:compile` on every deployment that changes CSS, JS or images:
in production the files are served from `public/assets/` only.

## 7. Cache

```bash
APP_ENV=prod bin/console cache:clear
```

## Routine deployment (after the first one)

Steps 2 and 3 are one-off. Later deployments only need:

```bash
git pull
APP_ENV=prod composer install --no-dev --optimize-autoloader
APP_ENV=prod bin/console doctrine:migrations:migrate --no-interaction
APP_ENV=prod bin/console asset-map:compile
APP_ENV=prod bin/console cache:clear
```
