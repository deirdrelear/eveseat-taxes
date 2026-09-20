<?php

namespace DeirdreLear\Seat\Taxes\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DeirdreLear\Seat\Taxes\Calculation\LegacyMath;
use DeirdreLear\Seat\Taxes\Calculation\LegacyOreTaxCalculator;
use DeirdreLear\Seat\Taxes\Calculation\LegacyRattingTaxCalculator;
use DeirdreLear\Seat\Taxes\Calculation\TaxClass;
use DeirdreLear\Seat\Taxes\Data\SourceRecord;
use DeirdreLear\Seat\Taxes\Models\DailyTaxFact;
use DeirdreLear\Seat\Taxes\Services\Sources\CharacterMiningResolver;
use DeirdreLear\Seat\Taxes\Services\Sources\MoonMiningResolver;
use DeirdreLear\Seat\Taxes\Services\Sources\RattingResolver;
use RuntimeException;
use Seat\Eveapi\Models\Corporation\CorporationInfo;
use Seat\Eveapi\Models\Sde\SolarSystem;

class DailyDryRunService
{
    private array $regionCache = [];
    private array $corporationTaxRateCache = [];
    private array $openingCarryCache = [];

    public function __construct(
        private MoonMiningResolver $moonMining,
        private CharacterMiningResolver $characterMining,
        private RattingResolver $ratting,
        private HistoricalOwnershipResolver $ownership,
        private SeatSdeAdapter $sde,
        private SeatPriceResolver $prices,
        private RuleSetService $rules,
        private LegacyOreTaxCalculator $oreCalculator,
        private LegacyRattingTaxCalculator $rattingCalculator,
    ) {
    }

    public function run(CarbonInterface $date): array
    {
        $day = CarbonImmutable::instance($date)->setTimezone('UTC')->startOfDay();
        $ruleSet = $this->rules->activeForDate($day);
        $this->rules->assertDryRunReady($ruleSet);

        $rates = $this->rules->rates($ruleSet);
        $scope = $this->rules->scope($ruleSet);
        $warnings = [];
        $details = [];
        $carry = [];

        $summary = [
            'gross_value' => 0,
            'tax_amount' => 0,
            'accepted_facts' => 0,
            'skipped_facts' => 0,
            'by_class' => [],
            'by_corporation' => [],
            'by_user' => [],
            'by_source' => [],
        ];

        foreach ($this->moonMining->recordsForDate($day) as $record) {
            $observerCorporationId = $record->payload['observer_owner_corporation_id'] ?? null;

            if (! $scope->allowsMiningHoldingCorporation($observerCorporationId)) {
                $summary['skipped_facts']++;
                $this->warn($warnings, 'moon_observer_outside_scope');
                continue;
            }

            $this->processOreRecord(
                record: $record,
                day: $day,
                allowedClasses: [
                    TaxClass::R4,
                    TaxClass::R8,
                    TaxClass::R16,
                    TaxClass::R32,
                    TaxClass::R64,
                ],
                rates: $rates,
                ruleSet: $ruleSet,
                scope: $scope,
                carry: $carry,
                warnings: $warnings,
                details: $details,
                summary: $summary,
            );
        }

        foreach ($this->characterMining->recordsForDate($day) as $record) {
            $regionId = $this->regionId(
                (int) ($record->payload['solar_system_id'] ?? 0)
            );

            if (! $scope->allowsMineralRegion($regionId)) {
                $summary['skipped_facts']++;
                $this->warn($warnings, 'character_mining_region_outside_scope');
                continue;
            }

            $this->processOreRecord(
                record: $record,
                day: $day,
                allowedClasses: [TaxClass::MINERAL, TaxClass::ICE],
                rates: $rates,
                ruleSet: $ruleSet,
                scope: $scope,
                carry: $carry,
                warnings: $warnings,
                details: $details,
                summary: $summary,
                extraPayload: ['region_id' => $regionId],
            );
        }

        $this->processRatting(
            day: $day,
            rates: $rates,
            scope: $scope,
            warnings: $warnings,
            details: $details,
            summary: $summary,
        );

        ksort($summary['by_class']);
        ksort($summary['by_corporation']);
        ksort($summary['by_user']);
        ksort($summary['by_source']);
        ksort($warnings);

        return [
            'date' => $day->toDateString(),
            'rule_set' => [
                'id' => $ruleSet->id,
                'name' => $ruleSet->name,
                'refine_efficiency' => (float) $ruleSet->refine_efficiency,
                'price_source' => $ruleSet->price_source,
                'rates' => $rates,
                'settings' => $ruleSet->settings,
            ],
            'summary' => $summary,
            'warnings' => $warnings,
            'details' => $details,
            'dry_run' => true,
            'writes_performed' => false,
            'limitations' => [
                'Opening ore remainder uses the latest canonical DailyTaxFact when available; otherwise it starts at zero.',
                'Historical corporation tax rate is not available in core SeAT; PvE dry-run uses current corporation_infos.tax_rate.',
                'Current SeAT user/main mapping is used and is not historically versioned by SeAT.',
                'Character mining ESI facts are daily; SeAT row time is observation time, not guaranteed exact in-game mining time.',
            ],
        ];
    }

    private function processOreRecord(
        SourceRecord $record,
        CarbonInterface $day,
        array $allowedClasses,
        array $rates,
        $ruleSet,
        $scope,
        array &$carry,
        array &$warnings,
        array &$details,
        array &$summary,
        array $extraPayload = [],
    ): void {
        if ($record->characterId === null || $record->typeId === null) {
            $summary['skipped_facts']++;
            $this->warn($warnings, 'ore_fact_missing_character_or_type');
            return;
        }

        $ore = $this->sde->oreDefinition($record->typeId);
        if ($ore === null) {
            $summary['skipped_facts']++;
            $this->warn($warnings, 'ore_type_missing_from_seat_sde');
            return;
        }

        $taxClass = $ore->taxClass();
        if (! in_array($taxClass, $allowedClasses, true)) {
            // This is expected for character mining when moon ore is also
            // present in the ESI character ledger: moon ore is accounted from
            // the corporation observer source to avoid double counting.
            $summary['skipped_facts']++;
            $this->warn($warnings, 'ore_class_not_used_by_source');
            return;
        }

        $recordedCorporationId = $record->source === 'moon_mining'
            ? $record->corporationId
            : null;

        $owner = $this->ownership->resolve(
            $record->characterId,
            $record->occurredAt,
            $recordedCorporationId
        );

        if (! $scope->allowsAlliance($owner->allianceId)) {
            $summary['skipped_facts']++;
            $this->warn($warnings, 'ore_owner_alliance_outside_scope');
            return;
        }

        if ($scope->excludesCorporation($owner->corporationId)) {
            $summary['skipped_facts']++;
            $this->warn($warnings, 'ore_owner_corporation_excluded');
            return;
        }

        if (! array_key_exists($taxClass, $rates)) {
            $summary['skipped_facts']++;
            $this->warn($warnings, 'missing_tax_rate_' . $taxClass);
            return;
        }

        $materialTypeIds = array_keys($ore->materials);
        $resolvedPrices = $this->prices->prices(
            $materialTypeIds,
            $ruleSet->price_source
        );

        $materialPrices = [];
        foreach ($resolvedPrices as $typeId => $price) {
            if ($price === null) {
                $this->warn($warnings, 'missing_price');
                continue;
            }

            $materialPrices[(int) $typeId] = $price;
        }

        $carryKey = $record->characterId . ':' . $record->typeId;
        if (! array_key_exists($carryKey, $carry)) {
            $carry[$carryKey] = $this->openingRemainder(
                $record->characterId,
                $record->typeId,
                $day
            );
        }

        $openingRemainder = $carry[$carryKey];
        $result = $this->oreCalculator->calculate(
            ore: $ore,
            quantity: (int) $record->quantity,
            openingRemainder: $openingRemainder,
            refineEfficiency: (float) $ruleSet->refine_efficiency,
            taxRate: (float) $rates[$taxClass],
            materialPrices: $materialPrices,
        );

        $carry[$carryKey] = $result->closingRemainder;

        $detail = [
            'source' => $record->source,
            'source_key' => $record->sourceKey,
            'occurred_at' => $record->occurredAt->toIso8601String(),
            'user_id' => $owner->userId,
            'main_character_id' => $owner->mainCharacterId,
            'character_id' => $record->characterId,
            'corporation_id' => $owner->corporationId,
            'alliance_id' => $owner->allianceId,
            'ownership_basis' => [
                'corporation' => $owner->corporationBasis,
                'alliance' => $owner->allianceBasis,
            ],
            'type_id' => $record->typeId,
            'tax_class' => $taxClass,
            'tax_rate' => (float) $rates[$taxClass],
            'price_source' => $ruleSet->price_source,
            'gross_value' => $result->grossValue,
            'tax_amount' => $result->taxAmount,
            'opening_remainder' => $openingRemainder,
            'closing_remainder' => $result->closingRemainder,
            'refined_batches' => $result->refinedBatches,
            'refined_materials' => $result->refinedMaterials,
            'material_values' => $result->materialValues,
            'material_taxes' => $result->materialTaxes,
            'payload' => array_merge($record->payload, $extraPayload),
        ];

        $details[] = $detail;
        $this->aggregate(
            $summary,
            source: $record->source,
            taxClass: $taxClass,
            corporationId: $owner->corporationId,
            userId: $owner->userId,
            grossValue: $result->grossValue,
            taxAmount: $result->taxAmount,
        );
    }

    private function processRatting(
        CarbonInterface $day,
        array $rates,
        $scope,
        array &$warnings,
        array &$details,
        array &$summary,
    ): void {
        if (! array_key_exists(TaxClass::RATTING, $rates)) {
            $this->warn($warnings, 'missing_tax_rate_ratting');
            return;
        }

        $groups = [];

        foreach ($this->ratting->recordsForDate($day) as $record) {
            if ($record->characterId === null || $record->corporationId === null) {
                $summary['skipped_facts']++;
                $this->warn($warnings, 'ratting_fact_missing_character_or_corporation');
                continue;
            }

            $owner = $this->ownership->resolve(
                $record->characterId,
                $record->occurredAt,
                $record->corporationId
            );

            if (! $scope->allowsAlliance($owner->allianceId)) {
                $summary['skipped_facts']++;
                $this->warn($warnings, 'ratting_owner_alliance_outside_scope');
                continue;
            }

            if ($scope->excludesWalletCorporation($record->corporationId)) {
                $summary['skipped_facts']++;
                $this->warn($warnings, 'ratting_corporation_excluded');
                continue;
            }

            $key = $record->characterId . ':' . $record->corporationId;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'character_id' => $record->characterId,
                    'corporation_id' => $record->corporationId,
                    'owner' => $owner,
                    'amount' => 0,
                    'source_keys' => [],
                    'ref_types' => [],
                ];
            }

            // Legacy RAtaxes deserialized wallet amounts into Int64.
            $groups[$key]['amount'] += LegacyMath::roundToInt((float) $record->amount);
            $groups[$key]['source_keys'][] = $record->sourceKey;
            $groups[$key]['ref_types'][$record->payload['ref_type']] = true;
        }

        foreach ($groups as $group) {
            $corporationTaxRate = $this->corporationTaxRate($group['corporation_id']);

            if ($corporationTaxRate === null || $corporationTaxRate <= 0) {
                $summary['skipped_facts'] += count($group['source_keys']);
                $this->warn($warnings, 'ratting_missing_or_zero_corporation_tax_rate');
                continue;
            }

            $this->warn($warnings, 'ratting_uses_current_corporation_tax_rate');

            $result = $this->rattingCalculator->calculate(
                corporationTaxReceived: $group['amount'],
                corporationTaxRate: $corporationTaxRate,
                allianceTaxRate: (float) $rates[TaxClass::RATTING],
            );

            $owner = $group['owner'];
            $details[] = [
                'source' => 'ratting_wallet',
                'source_key' => implode(',', $group['source_keys']),
                'occurred_at' => $day->toDateString(),
                'user_id' => $owner->userId,
                'main_character_id' => $owner->mainCharacterId,
                'character_id' => $group['character_id'],
                'corporation_id' => $group['corporation_id'],
                'alliance_id' => $owner->allianceId,
                'ownership_basis' => [
                    'corporation' => $owner->corporationBasis,
                    'alliance' => $owner->allianceBasis,
                ],
                'tax_class' => TaxClass::RATTING,
                'tax_rate' => (float) $rates[TaxClass::RATTING],
                'corporation_tax_rate' => $corporationTaxRate,
                'corporation_tax_rate_basis' => 'current_corporation_info',
                'gross_value' => $result->grossIncome,
                'tax_amount' => $result->allianceTax,
                'corporation_tax_received' => $result->corporationTaxReceived,
                'ref_types' => array_keys($group['ref_types']),
                'source_record_count' => count($group['source_keys']),
            ];

            $this->aggregate(
                $summary,
                source: 'ratting_wallet',
                taxClass: TaxClass::RATTING,
                corporationId: $group['corporation_id'],
                userId: $owner->userId,
                grossValue: $result->grossIncome,
                taxAmount: $result->allianceTax,
                factCount: count($group['source_keys']),
            );
        }
    }

    private function openingRemainder(
        int $characterId,
        int $typeId,
        CarbonInterface $day
    ): int {
        $key = $characterId . ':' . $typeId . ':' . $day->toDateString();

        if (array_key_exists($key, $this->openingCarryCache)) {
            return $this->openingCarryCache[$key];
        }

        $value = DailyTaxFact::query()
            ->where('character_id', $characterId)
            ->where('type_id', $typeId)
            ->whereDate('tax_date', '<', $day->toDateString())
            ->whereNotNull('closing_remainder')
            ->orderByDesc('tax_date')
            ->orderByDesc('id')
            ->value('closing_remainder');

        return $this->openingCarryCache[$key] = (int) ($value ?? 0);
    }

    private function regionId(int $solarSystemId): ?int
    {
        if ($solarSystemId <= 0) {
            return null;
        }

        if (! array_key_exists($solarSystemId, $this->regionCache)) {
            $value = SolarSystem::query()
                ->where('system_id', $solarSystemId)
                ->value('region_id');

            $this->regionCache[$solarSystemId] = $value !== null
                ? (int) $value
                : null;
        }

        return $this->regionCache[$solarSystemId];
    }

    private function corporationTaxRate(int $corporationId): ?float
    {
        if (! array_key_exists($corporationId, $this->corporationTaxRateCache)) {
            $value = CorporationInfo::query()
                ->where('corporation_id', $corporationId)
                ->value('tax_rate');

            $this->corporationTaxRateCache[$corporationId] = $value !== null
                ? (float) $value
                : null;
        }

        return $this->corporationTaxRateCache[$corporationId];
    }

    private function aggregate(
        array &$summary,
        string $source,
        string $taxClass,
        ?int $corporationId,
        ?int $userId,
        int $grossValue,
        int $taxAmount,
        int $factCount = 1,
    ): void {
        $summary['gross_value'] += $grossValue;
        $summary['tax_amount'] += $taxAmount;
        $summary['accepted_facts'] += $factCount;

        $this->addBucket($summary['by_class'], $taxClass, $grossValue, $taxAmount, $factCount);
        $this->addBucket($summary['by_source'], $source, $grossValue, $taxAmount, $factCount);

        if ($corporationId !== null) {
            $this->addBucket(
                $summary['by_corporation'],
                (string) $corporationId,
                $grossValue,
                $taxAmount,
                $factCount
            );
        }

        if ($userId !== null) {
            $this->addBucket(
                $summary['by_user'],
                (string) $userId,
                $grossValue,
                $taxAmount,
                $factCount
            );
        }
    }

    private function addBucket(
        array &$buckets,
        string $key,
        int $grossValue,
        int $taxAmount,
        int $factCount
    ): void {
        if (! isset($buckets[$key])) {
            $buckets[$key] = [
                'gross_value' => 0,
                'tax_amount' => 0,
                'facts' => 0,
            ];
        }

        $buckets[$key]['gross_value'] += $grossValue;
        $buckets[$key]['tax_amount'] += $taxAmount;
        $buckets[$key]['facts'] += $factCount;
    }

    private function warn(array &$warnings, string $key): void
    {
        $warnings[$key] = ($warnings[$key] ?? 0) + 1;
    }
}
