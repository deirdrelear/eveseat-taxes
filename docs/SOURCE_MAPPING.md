# SeAT source mapping

This document records the SeAT 5 tables and semantics used by the read-only Phase 1 resolvers.

## Moon mining

SeAT model:

`Seat\Eveapi\Models\Industry\CorporationIndustryMiningObserverData`

Table:

`corporation_industry_mining_observer_data`

Relevant fields:

- `id`
- `corporation_id` — corporation owning the observer data request
- `observer_id`
- `recorded_corporation_id` — corporation recorded by ESI for the mining entry
- `character_id`
- `type_id`
- `last_updated`
- `quantity`

For accounting ownership, `recorded_corporation_id` is preferred over a character's current affiliation.

## Character mining

SeAT model:

`Seat\Eveapi\Models\Industry\CharacterMining`

Table:

`character_minings`

Relevant fields:

- `id`
- `character_id`
- `date`
- `time`
- `solar_system_id`
- `type_id`
- `quantity`

SeAT stores deltas as it observes changes in the ESI daily mining ledger. The original ESI fact is daily; the stored `time` is useful operationally but is not guaranteed to be the exact in-game mining timestamp. A corporation change during the same UTC day is therefore an ambiguity that must be surfaced rather than silently guessed during historical bootstrap.

## Corporation wallet journal

SeAT model:

`Seat\Eveapi\Models\Wallet\CorporationWalletJournal`

Table:

`corporation_wallet_journals`

Relevant fields:

- `internal_id` — SeAT surrogate key
- `corporation_id`
- `division`
- `id` — ESI journal reference ID
- `date`
- `ref_type`
- `first_party_id`
- `second_party_id`
- `amount`
- `tax_receiver_id`
- `tax`

The legacy RAtaxes default ref types are:

- `bounty_prizes`
- `ess_escrow_transfer`
- `corporate_reward_payout`

They are configurable through `SEAT_TAXES_RATTING_REF_TYPES`.

## User/main mapping

SeAT stores character-to-user ownership in `refresh_tokens.user_id`. Soft-deleted refresh tokens are retained and can still provide historical account linkage.

The user's current main is stored in `users.main_character_id`.

A future accounting rule must explicitly define whether historical tax is attributed to the main character current at calculation time or whether the plugin should snapshot the main mapping daily. The architecture is designed to snapshot it.

## Historical corporation ownership

SeAT stores:

`character_corporation_histories`

with:

- `character_id`
- `start_date`
- `corporation_id`
- `record_id`

The resolver selects the newest history row whose `start_date <= event_time`. Current `character_affiliations` is only a fallback.

## Historical alliance ownership

SeAT stores:

`corporation_alliance_histories`

with:

- `corporation_id`
- `start_date`
- `alliance_id`
- `record_id`

The resolver selects the newest history row whose `start_date <= event_time`. Current `corporation_infos.alliance_id` is only a fallback.

## SDE

The plugin consumes SeAT SDE models/tables directly:

- `invTypes`
- `invGroups`
- `invTypeMaterials`

No SDE files are bundled with this plugin.

## Prices

SeAT `market_prices` is a current per-type snapshot. It includes ESI average/adjusted values and regional market-history statistics.

The regional history `highest`/`lowest` fields are historical traded-price extrema, not live Jita buy/sell quotes. They cannot be substituted for the old RAtaxes Fuzzwork Jita buy/sell/split modes.

Phase 2 snapshots every price actually used into `seat_taxes_price_snapshots`.
