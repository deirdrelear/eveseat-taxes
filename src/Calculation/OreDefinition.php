<?php

namespace DeirdreLear\Seat\Taxes\Calculation;

final class OreDefinition
{
    /**
     * @param array<int, int> $materials material type ID => quantity at 100% refine
     */
    public function __construct(
        public int $typeId,
        public int $categoryId,
        public int $groupId,
        public int $portionSize,
        public array $materials,
        public bool $published = true,
    ) {
    }

    public function taxClass(): string
    {
        return TaxClass::fromSde(
            categoryId: $this->categoryId,
            groupId: $this->groupId,
            published: $this->published,
        );
    }
}
