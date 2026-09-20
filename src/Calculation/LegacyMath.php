<?php

namespace DeirdreLear\Seat\Taxes\Calculation;

final class LegacyMath
{
    /**
     * Match .NET Math.Round(double) default midpoint behavior used by RAtaxes:
     * midpoint values are rounded to the nearest even integer.
     */
    public static function roundToInt(int|float $value): int
    {
        return (int) round((float) $value, 0, PHP_ROUND_HALF_EVEN);
    }
}
