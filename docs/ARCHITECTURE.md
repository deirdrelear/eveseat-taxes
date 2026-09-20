# Architecture

## Purpose

SeAT Taxes is a native SeAT plugin. SeAT remains the source of truth for ESI-derived facts and SDE data. The plugin owns only tax rules, normalized accounting snapshots, canonical daily results, and historical recalculation scenarios.

The old standalone RAtaxes application is a reference implementation for business formulas. Its ESI/SSO/SQLite/SDE infrastructure must not be ported.

## Core principles

1. **Read facts from SeAT DB.** No duplicate ESI client.
2. **Use SeAT SDE.** No bundled SDE parser or SDE snapshot.
3. **Close taxes daily in UTC.** Reports are sums of immutable daily accounting rows.
4. **Preserve historical inputs.** Current SeAT state must not silently rewrite old accounting.
5. **Version rules.** Every canonical result points to the rule set that produced it.
6. **Recalculations are scenarios.** A recalculation never overwrites canonical history.
7. **Keep auditability.** Store enough normalized data to explain how every tax value was produced.

## Data flow

```
SeAT DB
  |-- corporation_industry_mining_observer_data
  |-- character_minings
  |-- corporation_wallet_journals
  |-- refresh_tokens / users
  |-- character_corporation_histories
  |-- corporation_alliance_histories
  |-- market_prices
  |-- invTypes / invGroups / invTypeMaterials
  |
  v
Read-only fact + ownership + SDE resolvers
  |
  v
seat_taxes_daily_facts + seat_taxes_price_snapshots
  |
  +-- active TaxRuleSet
  v
seat_taxes_daily_results
  |
  +-- SUM() -> period reports
  |
  +-- Recalculation engine -> scenario results / delta
```

## Historical ownership

Moon observer data contains `recorded_corporation_id`; that is the preferred event-time corporation.

For sources that only provide a character, the plugin resolves the newest `character_corporation_histories` record whose `start_date` is not later than the event. Alliance ownership is resolved the same way from `corporation_alliance_histories`.

Current affiliation tables are fallback data only and the fallback basis must be recorded in the immutable accounting snapshot.

Character mining is a special case: the ESI source is daily, while SeAT's stored time is the time at which a quantity delta was observed. A same-day corporation transition can therefore be ambiguous and must be surfaced in diagnostics.

## Canonical calculation

A scheduled job will calculate a closed UTC day after SeAT has had time to ingest the final ESI data. The exact schedule is configurable; the initial scaffold deliberately does not enable the job.

The calculation must be idempotent. Source records need deterministic `source_key` values so rerunning a day updates or verifies the same facts rather than duplicating them.

## Recalculation modes

### Rate-only

Use the stored `taxable_value` and change only tax rates. This should be cheap even over years of history.

### Full replay

Used when changing rules that alter the tax base itself, for example:

- refine efficiency;
- price source;
- ore/material valuation policy;
- tax classification;
- character/main attribution policy.

Full replay should use immutable plugin snapshots when possible. Reading old SeAT source tables is a fallback and must be explicit because current affiliations, corporation tax rates, SDE, and market prices can differ from historical values.

## Important accounting decisions still to implement

- precise R4/R8/R16/R32/R64 classification from SeAT SDE;
- ordinary ore and ice classification;
- exact refine rounding policy matching RAtaxes;
- price source mapping;
- ratting gross-income derivation;
- main/user attribution policy;
- handling of corrections arriving from ESI after a day was closed;
- canonical rule-set activation workflow.

These must be backed by golden tests copied from known RAtaxes calculations before enabling scheduled calculations.
