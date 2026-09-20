<?php

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'DeirdreLear\\Seat\\Taxes\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

use DeirdreLear\Seat\Taxes\Calculation\LegacyMath;
use DeirdreLear\Seat\Taxes\Calculation\LegacyOreTaxCalculator;
use DeirdreLear\Seat\Taxes\Calculation\LegacyRattingTaxCalculator;
use DeirdreLear\Seat\Taxes\Calculation\OreDefinition;
use DeirdreLear\Seat\Taxes\Calculation\TaxClass;

function same(string $name, mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            sprintf(
                "FAIL %s\nExpected: %s\nActual:   %s\n",
                $name,
                var_export($expected, true),
                var_export($actual, true)
            )
        );
        exit(1);
    }

    fwrite(STDOUT, "PASS {$name}\n");
}

same('bankers rounding 2.5', 2, LegacyMath::roundToInt(2.5));
same('bankers rounding 3.5', 4, LegacyMath::roundToInt(3.5));

same(
    'R64 SDE group classification',
    TaxClass::R64,
    TaxClass::fromSde(25, 1923, true)
);
same(
    'ordinary asteroid classification',
    TaxClass::MINERAL,
    TaxClass::fromSde(25, 18, true)
);
same(
    'unpublished item is not taxable asteroid',
    TaxClass::NONE,
    TaxClass::fromSde(25, 1923, false)
);

$ore = new OreDefinition(
    typeId: 900001,
    categoryId: 25,
    groupId: 1923,
    portionSize: 100,
    materials: [34 => 415],
);

$oreCalculator = new LegacyOreTaxCalculator();

$result = $oreCalculator->calculate(
    ore: $ore,
    quantity: 250,
    openingRemainder: 0,
    refineEfficiency: 0.9063,
    taxRate: 0.20,
    materialPrices: [34 => 5.5],
);

same('legacy refine batches', 2, $result->refinedBatches);
same('legacy refine output floor', 752, $result->refinedMaterials[34]);
same('legacy closing remainder', 50, $result->closingRemainder);
same('legacy gross value', 4136, $result->grossValue);
same('legacy ore tax', 827, $result->taxAmount);
same('legacy ore class', TaxClass::R64, $result->taxClass);

$lostRemainder = $oreCalculator->calculate(
    ore: $ore,
    quantity: 20,
    openingRemainder: 50,
    refineEfficiency: 0.9063,
    taxRate: 0.20,
    materialPrices: [34 => 5.5],
);

same('legacy sub-portion produces no batch', 0, $lostRemainder->refinedBatches);
same(
    'legacy sub-portion remainder quirk is preserved',
    0,
    $lostRemainder->closingRemainder
);

$midpointOre = new OreDefinition(
    typeId: 900002,
    categoryId: 25,
    groupId: 1884,
    portionSize: 1,
    materials: [35 => 1],
);

$midpoint = $oreCalculator->calculate(
    ore: $midpointOre,
    quantity: 1,
    openingRemainder: 0,
    refineEfficiency: 1.0,
    taxRate: 0.5,
    materialPrices: [35 => 2.5],
);

same('legacy value uses .NET midpoint-to-even', 2, $midpoint->grossValue);
same('legacy tax uses .NET midpoint-to-even', 1, $midpoint->taxAmount);

$ratting = (new LegacyRattingTaxCalculator())->calculate(
    corporationTaxReceived: 100_000_000,
    corporationTaxRate: 0.10,
    allianceTaxRate: 0.08,
);

same('legacy ratting gross reconstruction', 1_000_000_000, $ratting->grossIncome);
same('legacy ratting alliance tax', 80_000_000, $ratting->allianceTax);

$rattingMidpoint = (new LegacyRattingTaxCalculator())->calculate(
    corporationTaxReceived: 1,
    corporationTaxRate: 0.4,
    allianceTaxRate: 0.5,
);

same('legacy ratting gross midpoint-to-even', 2, $rattingMidpoint->grossIncome);
same('legacy ratting tax midpoint-to-even', 1, $rattingMidpoint->allianceTax);

fwrite(STDOUT, "All golden compatibility tests passed.\n");
