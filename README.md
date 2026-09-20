# SeAT Taxes

Native SeAT plugin for daily EVE Online tax calculation, historical recalculation and reporting.

> **Status:** initial architecture scaffold. Tax calculation is intentionally not enabled yet.

## Goals

- read mining, wallet and ownership facts directly from the SeAT database;
- use the SDE already maintained by SeAT;
- calculate canonical tax once per closed UTC day;
- build period reports by summing daily results;
- keep immutable historical accounting inputs;
- support retrospective recalculation without rewriting canonical history;
- provide a native SeAT UI, permissions, scheduled jobs and queue jobs.

## Design

The plugin stores its own versioned rules, normalized daily facts, price snapshots, canonical results and recalculation scenarios. It does **not** duplicate SeAT's ESI client, SSO implementation or SDE loader.

See:

- [Architecture](docs/ARCHITECTURE.md)
- [Data model](docs/DATA_MODEL.md)
- [Roadmap](docs/ROADMAP.md)

## Compatibility target

The initial scaffold targets the current SeAT 5 plugin stack:

- PHP 8.1+
- Laravel 10
- `eveseat/services ^5.0.1`
- `eveseat/eveapi ^5.0.1`
- `eveseat/web ^5.0.1`

Exact supported SeAT versions will be tightened after the first integration test against a real SeAT installation.

## Development safety

Scheduled calculation is deliberately disabled in the scaffold. The next implementation phase is read-only source resolution plus golden calculation tests derived from the existing RAtaxes behavior.
