# SeAT Taxes

Native SeAT plugin for daily EVE Online tax calculation, historical recalculation and reporting.

> **Status:** Phase 1 read-only source resolution implemented. Canonical tax calculation is intentionally disabled.

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
- ratting candidates: `corporation_wallet_journals`;
- historical character corporation membership: `character_corporation_histories`;
- historical corporation alliance membership: `corporation_alliance_histories`;
- account/main mapping: `refresh_tokens` + `users`;
- SDE: `invTypes`, `invGroups`, `invTypeMaterials`.

Run source diagnostics without writing tax data:

```bash
php artisan taxes:diagnostics
php artisan taxes:diagnostics 2026-09-19
```

The same report is available from **Taxes -> Diagnostics** in the SeAT UI.

## Design

The plugin stores its own versioned rules, normalized daily facts, price snapshots, canonical results and recalculation scenarios. It does **not** duplicate SeAT's ESI client, SSO implementation or SDE loader.

See:

- [Architecture](docs/ARCHITECTURE.md)
- [Data model](docs/DATA_MODEL.md)
- [SeAT source mapping](docs/SOURCE_MAPPING.md)
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

Scheduled calculation remains disabled. The next phase is formula parity and golden tests derived from the existing RAtaxes behavior before any production tax values are written.
