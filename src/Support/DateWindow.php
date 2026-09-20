<?php

namespace DeirdreLear\Seat\Taxes\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class DateWindow
{
    public static function utcDay(CarbonInterface $date): array
    {
        $day = CarbonImmutable::instance($date)
            ->setTimezone('UTC')
            ->startOfDay();

        return [
            $day,
            $day->endOfDay(),
        ];
    }
}
