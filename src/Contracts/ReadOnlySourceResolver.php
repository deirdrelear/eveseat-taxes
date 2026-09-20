<?php

namespace DeirdreLear\Seat\Taxes\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Support\LazyCollection;

interface ReadOnlySourceResolver
{
    public function source(): string;

    public function recordsForDate(CarbonInterface $date): LazyCollection;

    public function summaryForDate(CarbonInterface $date): array;
}
