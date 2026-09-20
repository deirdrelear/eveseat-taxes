<?php

namespace DeirdreLear\Seat\Taxes\Commands;

use Carbon\CarbonImmutable;
use DeirdreLear\Seat\Taxes\Services\DailyDryRunService;
use Illuminate\Console\Command;
use Throwable;

class TaxesDryRun extends Command
{
    protected $signature = 'taxes:dry-run
        {date? : UTC date in YYYY-MM-DD; defaults to previous UTC day}
        {--details : Show accepted calculation rows}
        {--limit=100 : Maximum detail rows to print}';

    protected $description = 'Calculate one UTC day without writing canonical tax data';

    public function handle(DailyDryRunService $dryRun): int
    {
        try {
            $dateArg = $this->argument('date');
            $date = $dateArg
                ? $this->parseDate((string) $dateArg)
                : CarbonImmutable::now('UTC')->subDay()->startOfDay();

            $result = $dryRun->run($date);

            $this->info(
                sprintf(
                    'Dry-run for %s UTC using rule set #%d "%s"',
                    $result['date'],
                    $result['rule_set']['id'],
                    $result['rule_set']['name']
                )
            );
            $this->comment('NO TAX DATA WAS WRITTEN.');

            $summary = $result['summary'];
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Accepted source facts', $summary['accepted_facts']],
                    ['Skipped source facts', $summary['skipped_facts']],
                    ['Gross value', number_format($summary['gross_value'], 0, '.', ',')],
                    ['Tax', number_format($summary['tax_amount'], 0, '.', ',')],
                    ['Price source', $result['rule_set']['price_source']],
                    ['Refine efficiency', $result['rule_set']['refine_efficiency']],
                ]
            );

            $this->table(
                ['Tax class', 'Facts', 'Gross value', 'Tax'],
                collect($summary['by_class'])->map(function (array $bucket, string $key) {
                    return [
                        $key,
                        $bucket['facts'],
                        number_format($bucket['gross_value'], 0, '.', ','),
                        number_format($bucket['tax_amount'], 0, '.', ','),
                    ];
                })->values()->all()
            );

            if ($result['warnings'] !== []) {
                $this->warn('Warnings / compatibility caveats:');
                $this->table(
                    ['Warning', 'Count'],
                    collect($result['warnings'])
                        ->map(fn ($count, string $key) => [$key, $count])
                        ->values()
                        ->all()
                );
            }

            foreach ($result['limitations'] as $limitation) {
                $this->line(' - ' . $limitation);
            }

            if ($this->option('details')) {
                $limit = max(1, (int) $this->option('limit'));
                $rows = array_slice($result['details'], 0, $limit);

                $this->table(
                    ['Source', 'Character', 'Corporation', 'Class', 'Gross', 'Tax', 'Source key'],
                    array_map(function (array $row) {
                        return [
                            $row['source'],
                            $row['character_id'] ?? '-',
                            $row['corporation_id'] ?? '-',
                            $row['tax_class'],
                            number_format($row['gross_value'], 0, '.', ','),
                            number_format($row['tax_amount'], 0, '.', ','),
                            $row['source_key'],
                        ];
                    }, $rows)
                );

                if (count($result['details']) > $limit) {
                    $this->comment(
                        sprintf(
                            'Showing %d of %d accepted calculation rows. Increase --limit to see more.',
                            $limit,
                            count($result['details'])
                        )
                    );
                }
            }

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
}
