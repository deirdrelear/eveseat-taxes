<?php

namespace DeirdreLear\Seat\Taxes\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DeirdreLear\Seat\Taxes\Calculation\TaxClass;
use DeirdreLear\Seat\Taxes\Data\RuleScope;
use DeirdreLear\Seat\Taxes\Models\DailyTaxResult;
use DeirdreLear\Seat\Taxes\Models\TaxRuleSet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RuleSetService
{
    public function activeForDate(CarbonInterface $date): TaxRuleSet
    {
        $day = $date->copy()->setTimezone('UTC')->toDateString();

        $matches = TaxRuleSet::query()
            ->with('rates')
            ->whereDate('effective_from', '<=', $day)
            ->where(function ($query) use ($day) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $day);
            })
            ->orderByDesc('effective_from')
            ->get();

        if ($matches->isEmpty()) {
            throw new RuntimeException("No tax rule set is effective on {$day}.");
        }

        if ($matches->count() > 1) {
            throw new RuntimeException(
                "Multiple tax rule sets overlap on {$day}. Canonical calculation is ambiguous."
            );
        }

        return $matches->first();
    }

    public function rates(TaxRuleSet $ruleSet): array
    {
        if (! $ruleSet->relationLoaded('rates')) {
            $ruleSet->load('rates');
        }

        return $ruleSet->rates
            ->mapWithKeys(fn ($rate) => [$rate->tax_class => (float) $rate->rate])
            ->all();
    }

    public function scope(TaxRuleSet $ruleSet): RuleScope
    {
        return RuleScope::fromRuleSet($ruleSet);
    }

    public function assertDryRunReady(TaxRuleSet $ruleSet): void
    {
        $scope = $this->scope($ruleSet);

        if ($scope->allianceIds === []) {
            throw new RuntimeException(
                'Rule set has no alliance_ids. Refusing to calculate an unbounded SeAT dataset.'
            );
        }

        if (! in_array($ruleSet->price_source, [SeatPriceResolver::EVE_AVERAGE], true)) {
            throw new RuntimeException(
                "Unsupported native SeAT price source '{$ruleSet->price_source}'. "
                . 'Currently supported: eve_average.'
            );
        }
    }

    public function create(
        string $name,
        CarbonInterface $from,
        ?CarbonInterface $to,
        float $refineEfficiency,
        string $priceSource,
        array $rates,
        array $settings,
        ?int $createdByUserId = null,
    ): TaxRuleSet {
        $from = CarbonImmutable::instance($from)->setTimezone('UTC')->startOfDay();
        $to = $to ? CarbonImmutable::instance($to)->setTimezone('UTC')->startOfDay() : null;

        if ($to !== null && $to->lt($from)) {
            throw new RuntimeException('effective_to must not be earlier than effective_from.');
        }

        if ($this->overlaps($from, $to)) {
            throw new RuntimeException('The requested effective period overlaps an existing tax rule set.');
        }

        if ($refineEfficiency <= 0 || $refineEfficiency > 1) {
            throw new RuntimeException('Refine efficiency must be greater than 0 and not greater than 1.');
        }

        if (! in_array($priceSource, [SeatPriceResolver::EVE_AVERAGE], true)) {
            throw new RuntimeException(
                "Unsupported native SeAT price source '{$priceSource}'."
            );
        }

        $scope = new RuleScope(
            allianceIds: $this->ids($settings['alliance_ids'] ?? []),
            excludedCorporationIds: $this->ids($settings['excluded_corporation_ids'] ?? []),
            walletExcludedCorporationIds: $this->ids($settings['wallet_excluded_corporation_ids'] ?? []),
            mineralRegionIds: $this->ids($settings['mineral_region_ids'] ?? []),
            miningHoldingCorporationIds: $this->ids($settings['mining_holding_corporation_ids'] ?? []),
        );

        if ($scope->allianceIds === []) {
            throw new RuntimeException('At least one alliance ID is required.');
        }

        $normalizedRates = [];
        foreach (TaxClass::taxableClasses() as $taxClass) {
            if (! array_key_exists($taxClass, $rates)) {
                throw new RuntimeException("Missing rate for tax class {$taxClass}.");
            }

            $rate = (float) $rates[$taxClass];
            if ($rate < 0 || $rate > 1) {
                throw new RuntimeException("Rate for {$taxClass} must be between 0 and 1.");
            }

            $normalizedRates[$taxClass] = $rate;
        }

        $normalizedSettings = array_merge($settings, [
            'alliance_ids' => $scope->allianceIds,
            'excluded_corporation_ids' => $scope->excludedCorporationIds,
            'wallet_excluded_corporation_ids' => $scope->walletExcludedCorporationIds,
            'mineral_region_ids' => $scope->mineralRegionIds,
            'mining_holding_corporation_ids' => $scope->miningHoldingCorporationIds,
        ]);

        return DB::transaction(function () use (
            $name,
            $from,
            $to,
            $refineEfficiency,
            $priceSource,
            $normalizedRates,
            $normalizedSettings,
            $createdByUserId
        ) {
            $ruleSet = TaxRuleSet::create([
                'name' => $name,
                'effective_from' => $from->toDateString(),
                'effective_to' => $to?->toDateString(),
                'refine_efficiency' => $refineEfficiency,
                'price_source' => $priceSource,
                'settings' => $normalizedSettings,
                'created_by_user_id' => $createdByUserId,
            ]);

            foreach ($normalizedRates as $taxClass => $rate) {
                $ruleSet->rates()->create([
                    'tax_class' => $taxClass,
                    'rate' => $rate,
                ]);
            }

            return $ruleSet->load('rates');
        });
    }

    public function createLegacy(
        string $name,
        CarbonInterface $from,
        ?CarbonInterface $to,
        array $settings,
        ?int $createdByUserId = null,
    ): TaxRuleSet {
        $settings = array_merge($settings, [
            'compatibility_profile' => 'RAtaxes',
            'compatibility_note' => 'Legacy rates/formulas; EVE average is the native SeAT price source.',
        ]);

        return $this->create(
            name: $name,
            from: $from,
            to: $to,
            refineEfficiency: 0.906300,
            priceSource: SeatPriceResolver::EVE_AVERAGE,
            rates: [
                TaxClass::MINERAL => 0.10,
                TaxClass::ICE => 0.10,
                TaxClass::R4 => 0.10,
                TaxClass::R8 => 0.10,
                TaxClass::R16 => 0.10,
                TaxClass::R32 => 0.10,
                TaxClass::R64 => 0.20,
                TaxClass::RATTING => 0.08,
            ],
            settings: $settings,
            createdByUserId: $createdByUserId,
        );
    }

    public function close(TaxRuleSet $ruleSet, CarbonInterface $effectiveTo): TaxRuleSet
    {
        $effectiveTo = CarbonImmutable::instance($effectiveTo)
            ->setTimezone('UTC')
            ->startOfDay();

        if ($effectiveTo->lt($ruleSet->effective_from)) {
            throw new RuntimeException('Closing date must not be earlier than effective_from.');
        }

        if (
            $ruleSet->effective_to !== null
            && $effectiveTo->gt($ruleSet->effective_to)
        ) {
            throw new RuntimeException(
                'A closed rule set can only be shortened, not extended. Create a new version instead.'
            );
        }

        $hasLaterResults = DailyTaxResult::query()
            ->where('rule_set_id', $ruleSet->id)
            ->whereDate('tax_date', '>', $effectiveTo->toDateString())
            ->exists();

        if ($hasLaterResults) {
            throw new RuntimeException(
                'Cannot close this rule set: canonical results exist after the requested date.'
            );
        }

        $ruleSet->effective_to = $effectiveTo->toDateString();
        $ruleSet->save();

        return $ruleSet;
    }

    public function overlaps(
        CarbonInterface $from,
        ?CarbonInterface $to = null
    ): bool {
        $query = TaxRuleSet::query();

        if ($to !== null) {
            $query->whereDate('effective_from', '<=', $to->toDateString());
        }

        $query->where(function ($query) use ($from) {
            $query->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $from->toDateString());
        });

        return $query->exists();
    }

    private function ids(array $values): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $values),
            fn (int $id) => $id > 0
        )));
        sort($ids);

        return $ids;
    }
}
