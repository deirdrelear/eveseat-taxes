# Testing against a real SeAT instance

The current development branch is intentionally safe for source inspection: scheduled tax calculation is disabled and the available calculation path is dry-run only.

## 1. Install the development branch

The package is not on Packagist yet, so add this GitHub repository as a Composer VCS repository.

For a standard Blade installation, from the SeAT root (normally `/var/www/seat`):

```bash
sudo -H -u www-data bash -c 'php artisan down'

sudo -H -u www-data bash -c \
  'composer config repositories.eveseat-taxes vcs https://github.com/deirdrelear/eveseat-taxes'

sudo -H -u www-data bash -c \
  'composer require deirdrelear/eveseat-taxes:dev-feat/initial-plugin-scaffold'
```

Then run the standard SeAT plugin post-install steps:

```bash
sudo -H -u www-data bash -c 'php artisan vendor:publish --force --all'
sudo -H -u www-data bash -c 'php artisan migrate'
sudo -H -u www-data bash -c 'php artisan route:cache'
sudo -H -u www-data bash -c 'php artisan config:cache'
sudo -H -u www-data bash -c 'php artisan seat:cache:clear'
sudo -H -u www-data bash -c \
  'php artisan db:seed --class=Seat\\Services\\Database\\Seeders\\PluginDatabaseSeeder'

sudo -H -u www-data bash -c 'php artisan up'
```

The plugin schedule seeder currently registers **no tax calculation schedule**, by design.

For Docker, this development branch is awkward to use through `SEAT_PLUGINS` until a Packagist package/tag exists. For the first integration test, a Blade/dev installation or a custom Docker image with the VCS Composer repository is easier.

## 2. Grant permissions

The plugin registers:

- `taxes.view`
- `taxes.manage`
- `taxes.recalculate`

Grant the required permissions to the testing role/user in SeAT. A global superuser should already be able to access the pages.

The sidebar should expose:

- **Taxes -> Dashboard**
- **Taxes -> Rules**
- **Taxes -> Dry Run**
- **Taxes -> Diagnostics**

## 3. Verify source data

Run:

```bash
php artisan taxes:diagnostics
```

or inspect a specific closed UTC day:

```bash
php artisan taxes:diagnostics 2026-09-19
```

Check especially:

- missing SeAT/SDE tables;
- missing SDE type IDs;
- characters without SeAT user mapping;
- character mining without corporation history;
- wallet candidates without `universe_names` entries.

The same report is exposed in the SeAT UI under **Taxes -> Diagnostics**.

## 4. Create a rule set

You can do this in **Taxes -> Rules**.

The form defaults to the old RAtaxes rates, but every rate is editable before creation.

For CLI testing, the compatibility shortcut is:

```bash
php artisan taxes:rules:create-legacy 2026-09-01 \
  --alliance=123456789 \
  --mining-holding-corp=987654321 \
  --mineral-region=10000025
```

Repeat options for multiple values.

Legacy defaults:

- mineral 10%
- ice 10%
- R4 10%
- R8 10%
- R16 10%
- R32 10%
- R64 20%
- ratting 8%
- refine efficiency 90.63%
- price source: EVE average

Rule periods may not overlap. Rates/scope are not edited in place: close an old period and create a new version.

## 5. Dry-run one day

Use **Taxes -> Dry Run**, or CLI:

```bash
php artisan taxes:dry-run 2026-09-19
```

For sample rows:

```bash
php artisan taxes:dry-run 2026-09-19 --details --limit=100
```

Dry-run performs no writes to canonical tax data.

## 6. Compare with RAtaxes

For a useful parity check:

1. choose a historical UTC day with known moon/PvE/mining activity;
2. run old RAtaxes for exactly that day using EVE average prices;
3. run `taxes:dry-run` for the same UTC day;
4. compare totals by tax class and corporation;
5. inspect warnings and sample details for differences.

Expected sources of differences that must be investigated rather than hidden:

- old RAtaxes attributed some history using current corporation/main data;
- character-mining timestamps are daily ESI facts, not exact event times;
- PvE dry-run currently has only today's corporation tax rate available from core SeAT;
- old report-period remainder behavior differs from the canonical daily carry model.

Do not enable scheduled canonical accounting until those differences are understood on production-like samples.

## 7. Useful rollback

Until the plugin is merged/tagged, removal is simply the normal Composer package removal plus cache rebuild. Plugin-owned tables are deliberately prefixed `seat_taxes_`; do not drop them if you want to preserve test data for inspection.
