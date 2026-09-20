<?php

namespace DeirdreLear\Seat\Taxes\Services;

use Seat\Eveapi\Models\Sde\InvType;

class SeatSdeAdapter
{
    public function describeType(int $typeId): ?array
    {
        $type = InvType::query()
            ->with(['group', 'materials'])
            ->find($typeId);

        if (! $type) {
            return null;
        }

        return [
            'type_id' => (int) $type->typeID,
            'name' => $type->typeName,
            'group_id' => (int) $type->groupID,
            'group_name' => $type->group?->groupName,
            'category_id' => $type->group?->categoryID
                ? (int) $type->group->categoryID
                : null,
            'portion_size' => (int) $type->portionSize,
            'materials' => $type->materials->map(function ($material) {
                return [
                    'type_id' => (int) $material->typeID,
                    'name' => $material->typeName,
                    'quantity' => (int) $material->pivot->quantity,
                ];
            })->values()->all(),
        ];
    }
}
