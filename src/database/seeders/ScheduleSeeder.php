<?php

namespace DeirdreLear\Seat\Taxes\database\seeders;

use Seat\Services\Seeding\AbstractScheduleSeeder;

class ScheduleSeeder extends AbstractScheduleSeeder
{
    public function getSchedules(): array
    {
        // The scheduler entry will be enabled together with the calculation
        // engine. Keeping the initial scaffold unscheduled avoids silent or
        // misleading tax calculations before fact resolvers are implemented.
        return [];
    }

    public function getDeprecatedSchedules(): array
    {
        return [];
    }
}
