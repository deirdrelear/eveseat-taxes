<?php

$rattingRefTypes = array_values(array_filter(array_map(
    'trim',
    explode(',', env(
        'SEAT_TAXES_RATTING_REF_TYPES',
        'bounty_prizes,ess_escrow_transfer,corporate_reward_payout'
    ))
)));

$walletDivisions = array_values(array_filter(array_map(
    'intval',
    explode(',', env('SEAT_TAXES_WALLET_DIVISIONS', '1'))
)));

return [
    /*
     * Canonical calculation remains disabled until formula parity with the
     * existing RAtaxes implementation is covered by golden tests and tested
     * against a real SeAT installation.
     */
    'calculation_enabled' => env('SEAT_TAXES_CALCULATION_ENABLED', false),

    /*
     * Intended canonical calculation time for the previous UTC day.
     * The scheduler itself is not registered until Phase 3.
     */
    'daily_schedule' => env('SEAT_TAXES_DAILY_SCHEDULE', '30 1 * * *'),

    /*
     * Legacy RAtaxes wallet ref types by default. Operators can explicitly
     * override these without changing the compatibility calculation code.
     */
    'ratting_ref_types' => $rattingRefTypes,
    'wallet_divisions' => $walletDivisions,
];
