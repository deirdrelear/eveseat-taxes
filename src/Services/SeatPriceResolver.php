<?php

namespace DeirdreLear\Seat\Taxes\Services;

use InvalidArgumentException;
use Seat\Eveapi\Models\Market\Price;

class SeatPriceResolver
{
    public const EVE_AVERAGE = 'eve_average';

    /**
     * @return array<int, float|null>
     */
    public function prices(array $typeIds, string $source): array
    {
        $ids = array_values(array_unique(array_map('intval', $typeIds)));

        if ($ids === []) {
            return [];
        }

        if ($source !== self::EVE_AVERAGE) {
            throw new InvalidArgumentException(
                "Unsupported SeAT-native price source '{$source}'."
            );
        }

        $rows = Price::query()
            ->whereIn('type_id', $ids)
            ->get(['type_id', 'average_price'])
            ->keyBy('type_id');

        $result = [];
        foreach ($ids as $typeId) {
            $row = $rows->get($typeId);
            $result[$typeId] = $row ? (float) $row->average_price : null;
        }

        return $result;
    }

    public function price(int $typeId, string $source): ?float
    {
        return $this->prices([$typeId], $source)[$typeId] ?? null;
    }
}
