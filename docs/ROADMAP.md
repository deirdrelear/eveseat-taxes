# Roadmap

## Phase 0 - scaffold

- [x] native SeAT service provider;
- [x] routes, permissions, sidebar and minimal dashboard;
- [x] plugin-owned migrations and models;
- [x] architecture and data-model documentation;
- [x] syntax/composer CI.

## Phase 1 - read-only fact resolvers

- [x] Moon mining resolver from `corporation_industry_mining_observer_data`;
- [x] character mining resolver from `character_minings`;
- [x] ratting resolver from `corporation_wallet_journals`;
- [x] exact legacy wallet filter: division + ref type + second party category character;
- [x] SeAT SDE adapter for type/material/group data;
- [x] historical user/main/corporation/alliance ownership resolver;
- [x] read-only diagnostics page;
- [x] `taxes:diagnostics` CLI command;
- [ ] integration-test the resolvers against a real SeAT installation.

No taxes are written in this phase.

## Phase 2 - calculation parity

- [x] port legacy ore/refine/tax calculation into a pure compatibility core;
- [x] port legacy ratting calculation into a pure compatibility core;
- [x] reproduce .NET midpoint-to-even rounding;
- [x] preserve/document the legacy sub-portion remainder quirk;
- [x] golden compatibility fixtures runnable without a SeAT install;
- [x] bind SeAT SDE types/materials to the pure ore definition;
- [x] native SeAT EVE-average price resolver;
- [x] versioned scope/rate rule service;
- [x] explicit legacy-compatible rule-set creation command;
- [x] manual single-day dry-run calculation command;
- [ ] immutable price snapshots during canonical calculation;
- [ ] normalized daily fact writer;
- [ ] integration-test formula parity against known RAtaxes production samples.

## Phase 3 - daily canonical ledger

- [ ] idempotent daily calculation job;
- [ ] scheduler entry;
- [ ] late-data detection and controlled forward rebuild of refine carry chains;
- [ ] current-period and historical reports.

## Phase 4 - historical recalculation

- [ ] rate-only recalculation;
- [ ] full replay;
- [ ] delta reports for reimbursements/adjustments;
- [ ] audit metadata and permissions.

## Phase 5 - production hardening

- [ ] large-dataset query profiling;
- [ ] chunked/queued recalculation;
- [ ] failure recovery;
- [ ] DB indexes based on production query plans;
- [ ] installation/upgrade documentation.
