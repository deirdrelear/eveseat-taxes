<?php

$rattingRefTypes = array_values(array_filter(array_map(
    'trim',
    explode(',', env(
        'SEAT_TAXES_RATTING_REF_TYPES',
        'bounty_prizes,bounty_prize,ess_escrow_transfer'
    ))
)));

$walletDivisions = array_values(array_filter(array_map(
    'intval',
    explode(',', env('SEAT_TAXES_WALLET_DIVISIONS', '1'))
)));

return [
    /*
     * Canonical calculation remains disabled until formula parity with the
     * existing RAtaxes implementation is covered by golden tests.
     */
    'calculation_enabled' => env('SEAT_TAXES_CALCULATION_ENABLED', false),

    /*
     * Intended canonical calculation time for the previous UTC day.
     * The scheduler itself is not registered until Phase 3.
     */
    'daily_schedule' => env('SEAT_TAXES_DAILY_SCHEDULE', '30 1 * * *'),

    /*
     * Corporation wallet journal sources used as candidate ratting facts.
     * Exact accounting semantics are validated during formula-parity work.
     */
    'ratting_ref_types' => $rattingRefTypes,
    'wallet_divisions' => $walletDivisions,
];
