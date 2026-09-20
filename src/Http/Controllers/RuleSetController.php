<?php

namespace DeirdreLear\Seat\Taxes\Http\Controllers;

use Carbon\CarbonImmutable;
use DeirdreLear\Seat\Taxes\Calculation\TaxClass;
use DeirdreLear\Seat\Taxes\Models\TaxRuleSet;
use DeirdreLear\Seat\Taxes\Services\RuleSetService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class RuleSetController extends Controller
{
    public function index()
    {
        return view('taxes::rules', [
            'rule_sets' => TaxRuleSet::query()
                ->with('rates')
                ->orderByDesc('effective_from')
                ->get(),
            'tax_classes' => TaxClass::taxableClasses(),
        ]);
    }

    public function store(Request $request, RuleSetService $rules)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d'],
            'refine_efficiency' => ['required', 'numeric', 'gt:0', 'max:1'],
            'alliance_ids' => ['required', 'string'],
            'excluded_corporation_ids' => ['nullable', 'string'],
            'wallet_excluded_corporation_ids' => ['nullable', 'string'],
            'mineral_region_ids' => ['nullable', 'string'],
            'mining_holding_corporation_ids' => ['nullable', 'string'],
            'rate_mineral' => ['required', 'numeric', 'min:0', 'max:1'],
            'rate_ice' => ['required', 'numeric', 'min:0', 'max:1'],
            'rate_R4' => ['required', 'numeric', 'min:0', 'max:1'],
            'rate_R8' => ['required', 'numeric', 'min:0', 'max:1'],
            'rate_R16' => ['required', 'numeric', 'min:0', 'max:1'],
            'rate_R32' => ['required', 'numeric', 'min:0', 'max:1'],
            'rate_R64' => ['required', 'numeric', 'min:0', 'max:1'],
            'rate_ratting' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        try {
            $from = CarbonImmutable::createFromFormat('!Y-m-d', $validated['effective_from'], 'UTC');
            $to = ! empty($validated['effective_to'])
                ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['effective_to'], 'UTC')
                : null;

            $ruleSet = $rules->create(
                name: $validated['name'],
                from: $from,
                to: $to,
                refineEfficiency: (float) $validated['refine_efficiency'],
                priceSource: 'eve_average',
                rates: [
                    TaxClass::MINERAL => (float) $validated['rate_mineral'],
                    TaxClass::ICE => (float) $validated['rate_ice'],
                    TaxClass::R4 => (float) $validated['rate_R4'],
                    TaxClass::R8 => (float) $validated['rate_R8'],
                    TaxClass::R16 => (float) $validated['rate_R16'],
                    TaxClass::R32 => (float) $validated['rate_R32'],
                    TaxClass::R64 => (float) $validated['rate_R64'],
                    TaxClass::RATTING => (float) $validated['rate_ratting'],
                ],
                settings: [
                    'alliance_ids' => $this->parseIds($validated['alliance_ids']),
                    'excluded_corporation_ids' => $this->parseIds($validated['excluded_corporation_ids'] ?? ''),
                    'wallet_excluded_corporation_ids' => $this->parseIds($validated['wallet_excluded_corporation_ids'] ?? ''),
                    'mineral_region_ids' => $this->parseIds($validated['mineral_region_ids'] ?? ''),
                    'mining_holding_corporation_ids' => $this->parseIds($validated['mining_holding_corporation_ids'] ?? ''),
                ],
                createdByUserId: auth()->id() ? (int) auth()->id() : null,
            );

            return redirect()
                ->route('taxes.rules')
                ->with('success', "Created tax rule set #{$ruleSet->id}.");
        } catch (Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['rule_set' => $e->getMessage()]);
        }
    }

    public function close(Request $request, TaxRuleSet $ruleSet, RuleSetService $rules)
    {
        $validated = $request->validate([
            'effective_to' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            $effectiveTo = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $validated['effective_to'],
                'UTC'
            );

            $rules->close($ruleSet, $effectiveTo);

            return redirect()
                ->route('taxes.rules')
                ->with('success', "Closed tax rule set #{$ruleSet->id} at {$validated['effective_to']}.");
        } catch (Throwable $e) {
            return back()->withErrors(['rule_set' => $e->getMessage()]);
        }
    }

    private function parseIds(string $value): array
    {
        $tokens = preg_split('/[\s,;]+/', trim($value)) ?: [];

        $ids = array_values(array_unique(array_filter(
            array_map('intval', $tokens),
            fn (int $id) => $id > 0
        )));
        sort($ids);

        return $ids;
    }
}
