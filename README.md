# SeAT Taxes

Native SeAT plugin for daily EVE Online tax calculation, historical recalculation and reporting.

> **Status:** read-only SeAT source resolution, versioned rule scope and an RAtaxes-compatible dry-run calculation core are implemented. Canonical tax writes remain disabled.

## Goals

- read mining, wallet and ownership facts directly from the SeAT database;
- use the SDE already maintained by SeAT;
- calculate canonical tax once per closed UTC day;
- build period reports by summing daily results;
- keep immutable historical accounting inputs;
- support retrospective recalculation without rewriting canonical history;
- provide a native SeAT UI, permissions, scheduled jobs and queue jobs.

## Current read-only sources

- moon mining: `corporation_industry_mining_observer_data`;
- character mining: `character_minings`;
- ratting: configured `corporation_wallet_journals` ref types with `second_party=character`;
- historical character corporation membership: `character_corporation_histories`;
- historical corporation alliance membership: `corporation_alliance_histories`;
- account/main mapping: `refresh_tokens` + `users`;
- SDE: `invTypes`, `invGroups`, `invTypeMaterials`, `solar_systems`;
- native price source: SeAT `market_prices.average_price`.

Run source diagnostics without writing tax data:

```bash
php artisan taxes:diagnostics
php artisan taxes:diagnostics 2026-09-19
```

## Compatibility rule set and dry-run

Create an explicit legacy-compatible rule set before calculating anything:

```bash
php artisan taxes:rules:create-legacy 2026-09-01 \
  --alliance=123456789 \
  --mining-holding-corp=987654321 \
  --mineral-region=10000025
```

Then calculate one UTC day without writing tax data:

```bash
php artisan taxes:dry-run 2026-09-19
php artisan taxes:dry-run 2026-09-19 --details --limit=100
```

See [Testing](docs/TESTING.md) before using real SeAT data.

## Calculation compatibility

The pure calculation core reproduces the old RAtaxes refine/tax and ratting formulas, including .NET midpoint-to-even rounding and the old sub-portion remainder behavior.

Run the zero-dependency golden tests:

```bash
php tests/golden.php
```

See [RAtaxes calculation parity](docs/CALCULATION_PARITY.md) for the exact preserved semantics and the daily-ledger differences that are unavoidable when reports become simple sums of canonical daily results.

## Design

The plugin stores its own versioned rules, normalized daily facts, price snapshots, canonical results and recalculation scenarios. It does **not** duplicate SeAT's ESI client, SSO implementation or SDE loader.

See:

- [Architecture](docs/ARCHITECTURE.md)
- [Data model](docs/DATA_MODEL.md)
- [SeAT source mapping](docs/SOURCE_MAPPING.md)
- [RAtaxes calculation parity](docs/CALCULATION_PARITY.md)
- [Testing](docs/TESTING.md)
- [Roadmap](docs/ROADMAP.md)

## Compatibility target

The initial scaffold targets the SeAT 5 plugin stack:

- PHP 8.1+
- Laravel 10
- `eveseat/services ^5.0.1`
- `eveseat/eveapi ^5.0.1`
- `eveseat/web ^5.0.1`

Exact supported SeAT versions will be tightened after the first integration test against a real SeAT installation.

## Development safety

Scheduled calculation and canonical tax writes remain disabled until source resolvers, price snapshots and daily normalization are integration-tested against a real SeAT instance.
