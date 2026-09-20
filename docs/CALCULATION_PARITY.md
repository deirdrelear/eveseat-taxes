# RAtaxes calculation parity

This file records the exact legacy behaviors that the compatibility calculation core reproduces.

## Tax classification

RAtaxes considers an item an asteroid when it is published and its SDE category is 25.

Group mapping:

| Group ID | Tax class |
| ---: | --- |
| 465 | ice |
| 1884 | R4 |
| 1920 | R8 |
| 1921 | R16 |
| 1922 | R32 |
| 1923 | R64 |
| other group in category 25 | mineral |

## Refining

For a source ore record:

1. add the previous remainder for the same character/type;
2. `batches = floor(quantity / portionSize)`;
3. `closing remainder = quantity % portionSize`;
4. for each material, `output = floor(material_quantity * batches * refine_efficiency)`;
5. value each material separately;
6. round each material value using .NET `Math.Round(double)` behavior (midpoint-to-even);
7. apply the ore tax rate and round each material tax separately, also midpoint-to-even;
8. sum material values/taxes.

### Preserved legacy remainder quirk

The old `TypeMaterial.Refine` initializes `excess = 0` and only assigns a remainder when the combined quantity is at least one `portionSize`.

Therefore, if a record has less than one portion after adding an opening remainder, that combined sub-portion quantity is lost.

Example with portion 100:

- previous successful refine leaves 50;
- next record contains 20;
- combined quantity is 70;
- no refine occurs;
- old RAtaxes returns closing remainder 0.

The compatibility core intentionally preserves this. A corrected policy must be introduced as a new versioned accounting rule rather than silently changing historical semantics.

## Daily-ledger implication

Old RAtaxes created the remainder dictionary when a report was calculated, so remainders implicitly reset at the beginning of the selected report period.

A canonical daily ledger cannot depend on which later reporting period a user chooses. SeAT Taxes will therefore store opening/closing remainder with canonical daily facts and carry state forward through time.

This is a deliberate architectural difference required by the user's daily-calculation requirement. The per-record refine formula itself remains legacy-compatible.

## Ratting

RAtaxes:

1. sums corporation tax wallet amounts for a character;
2. divides by the corporation tax rate;
3. rounds reconstructed gross income midpoint-to-even;
4. multiplies gross income by the alliance tax rate;
5. rounds alliance tax midpoint-to-even.

The daily implementation must snapshot the corporation tax rate used for the day. Re-reading today's `corporation_infos.tax_rate` for historical transactions would not reproduce historical accounting.

## Prices

RAtaxes supported:

- EVE average;
- Jita sell;
- Jita buy;
- Jita split.

SeAT's core `market_prices` contains ESI average/adjusted prices plus regional **history** statistics. Regional history high/low values are not equivalent to live Jita sell/buy quotes and must not be mislabeled as such.

The first native SeAT price adapter will therefore support EVE average from SeAT DB. Additional price sources require a trustworthy SeAT-side database source and will be added explicitly rather than approximated.
