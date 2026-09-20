<?php

namespace DeirdreLear\Seat\Taxes\Calculation;

final class OreCalculationResult
{
    /**
     * @param array<int, int> $refinedMaterials
     * @param array<int, int> $materialValues
     * @param array<int, int> $materialTaxes
     */
    public function __construct(
        public int $inputQuantity,
        public int $openingRemainder,
        public int $combinedQuantity,
        public int $refinedBatches,
        public int $closingRemainder,
        public array $refinedMaterials,
        public array $materialValues,
        public array $materialTaxes,
        public int $grossValue,
        public int $taxAmount,
        public string $taxClass,
    ) {
    }

    public function toArray(): array
    {
        return [
            'input_quantity' => $this->inputQuantity,
            'opening_remainder' => $this->openingRemainder,
            'combined_quantity' => $this->combinedQuantity,
            'refined_batches' => $this->refinedBatches,
            'closing_remainder' => $this->closingRemainder,
            'refined_materials' => $this->refinedMaterials,
            'material_values' => $this->materialValues,
            'material_taxes' => $this->materialTaxes,
            'gross_value' => $this->grossValue,
            'tax_amount' => $this->taxAmount,
            'tax_class' => $this->taxClass,
        ];
    }
}
