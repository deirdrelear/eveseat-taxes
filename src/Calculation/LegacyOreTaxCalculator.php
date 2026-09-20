<?php

namespace DeirdreLear\Seat\Taxes\Calculation;

use InvalidArgumentException;

final class LegacyOreTaxCalculator
{
    /**
     * Reproduce CharacterTax.CalculateOreTax + TypeMaterial.Refine behavior
     * from RAtaxes for one ore record.
     *
     * @param array<int, int|float> $materialPrices material type ID => selected price
     */
    public function calculate(
        OreDefinition $ore,
        int $quantity,
        int $openingRemainder,
        float $refineEfficiency,
        float $taxRate,
        array $materialPrices,
    ): OreCalculationResult {
        if ($quantity < 0 || $openingRemainder < 0) {
            throw new InvalidArgumentException('Ore quantities and remainder must be non-negative.');
        }

        $combined = $quantity + $openingRemainder;
        $batches = 0;
        $closingRemainder = 0;
        $refined = [];
        $values = [];
        $taxes = [];
        $grossValue = 0;
        $taxAmount = 0;

        /*
         * This condition intentionally mirrors the old RAtaxes behavior.
         * If combined quantity is below portionSize, closing remainder stays
         * zero instead of preserving combined quantity.
         */
        if (
            $refineEfficiency > 0
            && $ore->portionSize > 0
            && $combined >= $ore->portionSize
        ) {
            $batches = intdiv($combined, $ore->portionSize);
            $closingRemainder = $combined % $ore->portionSize;

            foreach ($ore->materials as $materialTypeId => $materialQuantity) {
                $output = (int) floor($materialQuantity * $batches * $refineEfficiency);
                $refined[(int) $materialTypeId] = $output;

                if (! array_key_exists($materialTypeId, $materialPrices)) {
                    continue;
                }

                $value = LegacyMath::roundToInt(
                    $output * (float) $materialPrices[$materialTypeId]
                );
                $tax = LegacyMath::roundToInt($value * $taxRate);

                $values[(int) $materialTypeId] = $value;
                $taxes[(int) $materialTypeId] = $tax;
                $grossValue += $value;
                $taxAmount += $tax;
            }
        }

        return new OreCalculationResult(
            inputQuantity: $quantity,
            openingRemainder: $openingRemainder,
            combinedQuantity: $combined,
            refinedBatches: $batches,
            closingRemainder: $closingRemainder,
            refinedMaterials: $refined,
            materialValues: $values,
            materialTaxes: $taxes,
            grossValue: $grossValue,
            taxAmount: $taxAmount,
            taxClass: $ore->taxClass(),
        );
    }
}
