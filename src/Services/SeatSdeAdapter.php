<?php

namespace DeirdreLear\Seat\Taxes\Services;

use DeirdreLear\Seat\Taxes\Calculation\OreDefinition;
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
            'published' => (bool) $type->published,
            'materials' => $type->materials->map(function ($material) {
                return [
                    'type_id' => (int) $material->typeID,
                    'name' => $material->typeName,
                    'quantity' => (int) $material->pivot->quantity,
                ];
            })->values()->all(),
        ];
    }

    public function oreDefinition(int $typeId): ?OreDefinition
    {
        $type = $this->describeType($typeId);

        if (! $type || $type['category_id'] === null) {
            return null;
        }

        $materials = [];
        foreach ($type['materials'] as $material) {
            $materials[$material['type_id']] = $material['quantity'];
        }

        return new OreDefinition(
            typeId: $type['type_id'],
            categoryId: $type['category_id'],
            groupId: $type['group_id'],
            portionSize: $type['portion_size'],
            materials: $materials,
            published: $type['published'],
        );
    }
}
