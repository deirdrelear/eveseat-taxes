# Testing against a real SeAT instance

The current branch is intentionally safe to install for source inspection: scheduled tax calculation is disabled and the available calculation command is dry-run only.

## 1. Install the development branch

Use the normal SeAT community-package Composer workflow, but point Composer at this GitHub repository/branch until a tagged package is published.

After Composer has loaded the package, run the normal SeAT package discovery/migration steps for your installation.

Do not enable `SEAT_TAXES_CALCULATION_ENABLED`; the canonical writer and scheduler do not exist yet.

## 2. Verify source data

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

## 3. Create an explicit compatibility rule set

Example only — substitute the real alliance, holding corporation and taxable regions:

```bash
php artisan taxes:rules:create-legacy 2026-09-01 \
  --alliance=123456789 \
  --mining-holding-corp=987654321 \
  --mineral-region=10000025
```

Repeat options for multiple values.

The command creates the old RAtaxes default rates:

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

Rule periods may not overlap.

## 4. Dry-run one day

```bash
php artisan taxes:dry-run 2026-09-19
```

For sample rows:

```bash
php artisan taxes:dry-run 2026-09-19 --details --limit=100
```

Dry-run performs no writes to canonical tax data.

## 5. Compare with RAtaxes

For a useful parity check:

1. choose a historical UTC day with known moon/PvE/mining activity;
2. run the old RAtaxes report for exactly that day using EVE average prices;
3. run `taxes:dry-run` for the same UTC day;
4. compare totals by class and corporation;
5. inspect warnings and details for differences.

Expected sources of differences that must be investigated rather than hidden:

- old RAtaxes attributed some history using current corporation/main data;
- character-mining timestamps are daily ESI facts, not exact event times;
- PvE dry-run currently has only today's corporation tax rate available from core SeAT;
- old report-period remainder behavior differs from the canonical daily carry model.

Do not enable scheduled canonical accounting until those differences are understood on production-like samples.
