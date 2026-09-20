<?php

return [
    /*
     * UTC time at which the previous UTC day becomes eligible for canonical
     * calculation. The calculation engine is intentionally disabled in the
     * initial scaffold until the fact resolvers are implemented and tested.
     */
    'calculation_enabled' => env('SEAT_TAXES_CALCULATION_ENABLED', false),
    'daily_schedule' => env('SEAT_TAXES_DAILY_SCHEDULE', '30 1 * * *'),
];
