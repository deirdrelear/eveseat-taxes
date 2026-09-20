<?php

namespace DeirdreLear\Seat\Taxes\Commands;

use Carbon\CarbonImmutable;
use DeirdreLear\Seat\Taxes\Services\RuleSetService;
use Illuminate\Console\Command;
use Throwable;

class CreateLegacyRuleSet extends Command
{
    protected $signature = 'taxes:rules:create-legacy
        {effective_from : First UTC day in YYYY-MM-DD}
        {--effective-to= : Optional last UTC day in YYYY-MM-DD}
        {--name=RAtaxes legacy compatibility : Rule set name}
        {--alliance=* : Alliance ID to include; repeat option for multiple alliances}
        {--exclude-corp=* : Corporation ID excluded from all taxes}
        {--wallet-exclude-corp=* : Corporation ID excluded from wallet/PvE tax}
        {--mineral-region=* : Region ID where ordinary ore/ice mining is taxable}
        {--mining-holding-corp=* : Corporation owning moon-mining observers}';

    protected $description = 'Create a versioned rule set matching the legacy RAtaxes defaults';

    public function handle(RuleSetService $rules): int
    {
        try {
            $from = $this->parseDate((string) $this->argument('effective_from'));
            $toOption = $this->option('effective-to');
            $to = $toOption ? $this->parseDate((string) $toOption) : null;

            $settings = [
                'alliance_ids' => $this->ids($this->option('alliance')),
                'excluded_corporation_ids' => $this->ids($this->option('exclude-corp')),
                'wallet_excluded_corporation_ids' => $this->ids($this->option('wallet-exclude-corp')),
                'mineral_region_ids' => $this->ids($this->option('mineral-region')),
                'mining_holding_corporation_ids' => $this->ids($this->option('mining-holding-corp')),
            ];

            $ruleSet = $rules->createLegacy(
                name: (string) $this->option('name'),
                from: $from,
                to: $to,
                settings: $settings,
            );

            $this->info("Created rule set #{$ruleSet->id}: {$ruleSet->name}");
            $this->line('Effective from: ' . $from->toDateString());
            $this->line('Effective to: ' . ($to?->toDateString() ?? 'open-ended'));
            $this->line('Alliances: ' . implode(', ', $settings['alliance_ids']));
            $this->line(
                'Mineral regions: '
                . ($settings['mineral_region_ids'] === []
                    ? 'none (ordinary ore/ice disabled)'
                    : implode(', ', $settings['mineral_region_ids']))
            );
            $this->line(
                'Moon observer corporations: '
                . ($settings['mining_holding_corporation_ids'] === []
                    ? 'none (moon source disabled by scope)'
                    : implode(', ', $settings['mining_holding_corporation_ids']))
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function parseDate(string $value): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');

        if (! $date || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException("Invalid date '{$value}'. Use YYYY-MM-DD.");
        }

        return $date;
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
