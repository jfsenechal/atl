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

## 8. Scheduled invoice sending (once)

Invoices planned in the admin (`FactureCron`) are sent by the
`mercredi:facture-send` command. The public URL `/cron/launch` no longer exists
(it returns a 404).

OVH shared hosting runs a PHP file without arguments, so the scheduled task
points to the wrapper script `bin/cron-facture.php`, which runs the command.

In the OVH control panel: Web Cloud → Hosting plans → the hosting → **More** →
**Scheduled tasks - Cron** → **Add a scheduled task**:

- Command to execute: path of `bin/cron-facture.php` relative to the hosting
  root (for example `www/bin/cron-facture.php` if the project is in `www/`)
- Language: the PHP version of the project (8.5)
- Frequency: every hour, every day

Then delete the old task that calls `https://atl-hotton.be/cron/launch`.

Good to know:

- `APP_ENV=prod`, `MAILER_DSN` and `LOCK_DSN` are read from `.env.local` on the
  server, as for the website.
- Each run sends at most 40 invoices per `FactureCron`, and a `FactureCron` is
  processed at most once every 60 minutes; it is marked done when no invoice is
  left to send.
- Invoices, admin alerts and the run report are sent directly over SMTP, not
  through the Messenger queue. Invoices count against the hourly mail quota
  (see section 9).
- A lock (`symfony/lock`) makes a run exit immediately if another one is still
  in progress.

To check mail sending from the command line (over SSH), or to run a batch by hand:

```bash
APP_ENV=prod bin/console mailer:test you@example.com
APP_ENV=prod bin/console mercredi:facture-send
```

The output of each scheduled run is in the OVH cron logs (hosting → **More** →
**Logs**), and the report is mailed after each run that did something.

Both cron scripts (`bin/cron-facture.php`, `bin/cron-messenger.php`) go through
`bin/cron-bootstrap.php`, which prints a START line (PHP version, `APP_ENV`,
`memory_limit`, `max_execution_time`), runs the command in verbose mode, prints
any exception or PHP fatal error with file, line and stack trace, and ends with
an END line (exit code, duration, peak memory). A one-line summary of every run
is also appended to `var/log/cron.log`.

OVH disables a scheduled task after 10 consecutive failed runs (exit code other
than 0, or too long). Read `var/log/cron.log` or the OVH cron logs, fix the
cause, run the script once by hand (`php bin/cron-facture.php`), then re-enable
the task in the OVH control panel.

## 9. Mail queue worker (once)

Mails are sent directly by default (account mails, login links, contact form,
an invoice sent from the admin, admin notifications...). Only mass mailings, the
messages sent from the admin to many parents, are put in the Messenger `async`
queue (database table `messenger_messages`), one mail per recipient. They only
leave when the queue is processed, so a scheduled task must run the worker.
Invoices in bulk are not queued: they go through the invoice cron (section 8).

In the OVH control panel, add a second scheduled task, like the invoice one:

- Command to execute: path of `bin/cron-messenger.php` relative to the hosting
  root (for example `www/bin/cron-messenger.php`)
- Language: the PHP version of the project (8.5)
- Frequency: every hour, every day

The script runs `messenger:consume async` for 55 minutes, so mails leave within
seconds while it runs.

OVH shared hosting accepts a limited number of mails per hour. The `mailer` rate
limiter caps sending at `MAILER_HOURLY_LIMIT` mails per hour (100 by default, set
it in `.env.local`). The worker and the invoice cron share this quota: when it is
used up, the worker waits and the invoice cron stops and continues on its next
run.

The page **Paramètres → Diagnostic des mails** (`/admin/mail-debug/`) shows the
mails waiting or failed, the quota left this hour, sends a test mail with the
SMTP conversation, and can process the queue by hand.

## Routine deployment (after the first one)

Steps 2, 3 and 8 are one-off. Later deployments only need:

```bash
git pull
APP_ENV=prod composer install --no-dev --optimize-autoloader
APP_ENV=prod bin/console doctrine:migrations:migrate --no-interaction
APP_ENV=prod bin/console asset-map:compile
APP_ENV=prod bin/console cache:clear
```
