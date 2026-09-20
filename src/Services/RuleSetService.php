<?php

namespace DeirdreLear\Seat\Taxes\Services;

use Carbon\CarbonInterface;
use DeirdreLear\Seat\Taxes\Data\RuleScope;
use DeirdreLear\Seat\Taxes\Models\TaxRuleSet;
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

        if (! in_array($ruleSet->price_source, ['eve_average'], true)) {
            throw new RuntimeException(
                "Unsupported native SeAT price source '{$ruleSet->price_source}'. "
                . 'Currently supported: eve_average.'
            );
        }
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
}
