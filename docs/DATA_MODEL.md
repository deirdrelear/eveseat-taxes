# Data model

## seat_taxes_rule_sets

A versioned set of accounting rules with an effective date range. A rule set is not edited retroactively after it has produced canonical results; create a new version instead.

## seat_taxes_rule_rates

Per-class rates for a rule set. Initial target classes:

- mineral
- ice
- R4
- R8
- R16
- R32
- R64
- ratting

The list is intentionally represented as data rather than hard-coded columns.

## seat_taxes_price_snapshots

Historical prices actually used by tax accounting. SeAT's `market_prices` is a current snapshot keyed by type and is not sufficient to reproduce historical calculations by itself.

## seat_taxes_daily_facts

Normalized immutable accounting inputs. These are derived from SeAT DB and retain event-time ownership and valuation inputs.

The `source_key` is a deterministic identifier of the SeAT-origin fact. It makes daily ingestion idempotent.

## seat_taxes_daily_results

Canonical tax result for one normalized fact under one rule set.

Period reports should primarily aggregate this table rather than recalculate raw SeAT history.

## seat_taxes_recalculation_runs

Metadata and overrides for a historical scenario. Canonical results are never changed by a scenario.

Modes:

- `rate_only`
- `full_replay`

## seat_taxes_recalculation_results

Scenario output with original value, recalculated value, and delta. It can be aggregated by corporation, user, date, or tax class.
