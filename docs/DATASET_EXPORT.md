# Production test-dataset export

The exporter creates a **sanitized, scoped dataset** from a production SeAT database so tax calculations can be reproduced on a separate test installation.

It deliberately does **not** create a full database dump.

There are two ways to run it:

1. an Artisan command when the development plugin is already installed;
2. a standalone wrapper which does **not** install/register the plugin in production.

For a production SeAT, the standalone wrapper is the preferred first test.

## Standalone production export

Clone or copy this repository to the SeAT host. It does not need to be inside
`/var/www/seat`.

Example:

```bash
cd /opt
git clone -b feat/initial-plugin-scaffold \
  https://github.com/deirdrelear/eveseat-taxes.git

cd /opt/eveseat-taxes

php tools/export-dataset.php \
  --seat-root=/var/www/seat \
  --from=2026-07-01 \
  --to=2026-09-19 \
  --alliance=99001234 \
  --holding-corp=98001234 \
  --mineral-region=10000025
```

The wrapper only bootstraps the existing SeAT Laravel application so it can
reuse the configured database connection. It does not register the plugin,
run migrations, seed schedules or write to SeAT tables.

Run it as the same user that normally owns/runs SeAT files if permissions
require it, for example:

```bash
sudo -H -u www-data php tools/export-dataset.php \
  --seat-root=/var/www/seat \
  --from=2026-07-01 \
  --to=2026-09-19 \
  --alliance=99001234
```

## Artisan command

If the plugin branch is already installed:

```bash
php artisan taxes:dataset:export FROM TO \
  --alliance=ALLIANCE_ID
```

Example:

```bash
php artisan taxes:dataset:export 2026-07-01 2026-09-19 \
  --alliance=99001234 \
  --holding-corp=98001234 \
  --mineral-region=10000025
```

Options may be repeated:

```bash
--alliance=99000001 --alliance=99000002
--holding-corp=98000001 --holding-corp=98000002
--mineral-region=10000025 --mineral-region=10000014
```

By default wallet rows are restricted to the legacy RAtaxes ref types:

- `bounty_prizes`
- `ess_escrow_transfer`
- `corporate_reward_payout`

With the Artisan command those defaults come from `taxes.ratting_ref_types`.
They can be overridden explicitly:

```bash
--wallet-ref-type=bounty_prizes \
--wallet-ref-type=ess_escrow_transfer \
--wallet-ref-type=corporate_reward_payout
```

## Default output

Without `--output`, a new private directory is created under the SeAT
installation's:

```text
storage/app/seat-taxes-datasets/
```

Example:

```text
storage/app/seat-taxes-datasets/taxes_20260701_20260919_20260920_190000/
├── README.txt
├── manifest.json
├── corporation_industry_mining_observer_data.jsonl.gz
├── character_minings.jsonl.gz
├── corporation_wallet_journals.jsonl.gz
├── character_corporation_histories.jsonl.gz
├── corporation_alliance_histories.jsonl.gz
├── character_affiliations.jsonl.gz
├── corporation_infos.jsonl.gz
├── character_user_map.jsonl.gz
├── users.jsonl.gz
├── invTypeMaterials.jsonl.gz
├── invTypes.jsonl.gz
├── invGroups.jsonl.gz
├── market_prices.jsonl.gz
├── solar_systems.jsonl.gz
└── universe_names.jsonl.gz
```

Each table/projection is newline-delimited JSON compressed with gzip.

The manifest records:

- date range and requested scope;
- SeAT package versions;
- row counts;
- exported columns;
- compressed sizes;
- SHA-256 of the uncompressed JSONL stream;
- security metadata.

## Scope selection

The exporter first finds corporations which have belonged to one of the requested alliances by the end of the export period. It then finds characters which have belonged to those corporations.

Only tax-relevant source facts for that candidate population are exported.

Historical affiliation rows up to the dataset end date are included because an event near the start of the requested period may depend on a corporation/alliance membership which began earlier.

For character mining, `--mineral-region` additionally restricts rows by SeAT's `solar_systems.region_id`.

For moon mining, `--holding-corp` restricts the observer-owner corporation. If it is omitted, all observer-owner corporations are allowed, while the mined character/corporation population is still restricted to the requested alliances.

## Security

The exporter intentionally excludes:

- OAuth refresh tokens;
- ESI access tokens;
- scopes and scope profiles;
- character owner hashes;
- passwords;
- remember tokens;
- email addresses.

`character_user_map.jsonl.gz` is only a sanitized projection of
`refresh_tokens` containing the identity mapping required for tax aggregation.

`users.jsonl.gz` only contains the user ID, display name and main-character ID.

The dataset **does contain mining and corporation-wallet history**, so it should still be treated as confidential and transferred/stored accordingly.

Files are created with best-effort mode `0600` and the dataset directory with `0700`.

## Failure behavior

The exporter streams rows instead of loading source tables into memory.

If the export fails after creating the destination directory, it leaves the partial files in place and writes:

```text
EXPORT_FAILED.txt
```

Do not import a dataset directory containing that marker.

## Import

An importer is intentionally a separate step. Do not load these JSONL files into core SeAT tables manually.

The importer will be responsible for collision-safe user mapping and for reconstructing only the minimum safe identity data needed by the test installation.
