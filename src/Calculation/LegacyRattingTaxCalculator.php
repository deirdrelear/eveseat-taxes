<?php

namespace DeirdreLear\Seat\Taxes\Calculation;

final class LegacyRattingTaxCalculator
{
    /**
     * Reproduce CharacterTax.CalculateRatTax from RAtaxes.
     */
    public function calculate(
        int $corporationTaxReceived,
        float $corporationTaxRate,
        float $allianceTaxRate,
    ): RattingCalculationResult {
        if ($corporationTaxRate <= 0 || $corporationTaxReceived === 0) {
            return new RattingCalculationResult(
                corporationTaxReceived: $corporationTaxReceived,
                corporationTaxRate: $corporationTaxRate,
                grossIncome: 0,
                allianceTaxRate: $allianceTaxRate,
                allianceTax: 0,
            );
        }

        $grossIncome = LegacyMath::roundToInt(
            $corporationTaxReceived / $corporationTaxRate
        );

        $allianceTax = LegacyMath::roundToInt(
            $grossIncome * $allianceTaxRate
        );

        return new RattingCalculationResult(
            corporationTaxReceived: $corporationTaxReceived,
            corporationTaxRate: $corporationTaxRate,
            grossIncome: $grossIncome,
            allianceTaxRate: $allianceTaxRate,
            allianceTax: $allianceTax,
        );
    }
}
