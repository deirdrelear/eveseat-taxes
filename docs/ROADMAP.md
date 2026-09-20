# Roadmap

## Phase 0 - scaffold

- native SeAT service provider;
- routes, permissions, sidebar and minimal dashboard;
- plugin-owned migrations and models;
- architecture and data-model documentation;
- syntax/composer CI.

## Phase 1 - read-only fact resolvers

- Moon mining resolver from `corporation_industry_mining_observer_data`;
- character mining resolver from `character_minings`;
- ratting resolver from `corporation_wallet_journals`;
- SeAT SDE adapter for type/material/group data;
- user/main/corporation ownership resolver;
- diagnostics page showing missing source data/scopes.

No taxes are written in this phase.

## Phase 2 - calculation parity

- port RAtaxes tax formulas;
- implement price snapshots;
- implement tax rule sets and activation;
- golden fixtures proving parity with known RAtaxes reports;
- manual single-day calculation command.

## Phase 3 - daily canonical ledger

- idempotent daily calculation job;
- scheduler entry;
- late-data detection and controlled day rebuild;
- current-period and historical reports.

## Phase 4 - historical recalculation

- rate-only recalculation;
- full replay;
- delta reports for reimbursements/adjustments;
- audit metadata and permissions.

## Phase 5 - production hardening

- large-dataset query profiling;
- chunked/queued recalculation;
- failure recovery;
- DB indexes based on production query plans;
- installation/upgrade documentation.
