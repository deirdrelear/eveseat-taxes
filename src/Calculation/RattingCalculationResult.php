<?php

namespace DeirdreLear\Seat\Taxes\Calculation;

final class RattingCalculationResult
{
    public function __construct(
        public int $corporationTaxReceived,
        public float $corporationTaxRate,
        public int $grossIncome,
        public float $allianceTaxRate,
        public int $allianceTax,
    ) {
    }

    public function toArray(): array
    {
        return [
            'corporation_tax_received' => $this->corporationTaxReceived,
            'corporation_tax_rate' => $this->corporationTaxRate,
            'gross_income' => $this->grossIncome,
            'alliance_tax_rate' => $this->allianceTaxRate,
            'alliance_tax' => $this->allianceTax,
        ];
    }
}
