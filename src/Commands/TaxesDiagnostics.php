<?php

namespace DeirdreLear\Seat\Taxes\Commands;

use Carbon\CarbonImmutable;
use DeirdreLear\Seat\Taxes\Services\DiagnosticsService;
use Illuminate\Console\Command;
use Throwable;

class TaxesDiagnostics extends Command
{
    protected $signature = 'taxes:diagnostics {date? : UTC date in YYYY-MM-DD}';

    protected $description = 'Inspect SeAT tax source data without writing tax data';

    public function handle(DiagnosticsService $diagnostics): int
    {
        try {
            $dateArg = $this->argument('date');
            $date = $dateArg
                ? CarbonImmutable::createFromFormat('Y-m-d', $dateArg, 'UTC')->startOfDay()
                : CarbonImmutable::now('UTC')->subDay()->startOfDay();

            if (! $date) {
                $this->error('Invalid date. Use YYYY-MM-DD.');

                return self::FAILURE;
            }

            $report = $diagnostics->report($date);

            $this->info('SeAT Taxes diagnostics for ' . $report['date'] . ' UTC');

            if (! $report['ready']) {
                $this->error('Required SeAT tables are missing:');
                foreach ($report['missing_tables'] as $table) {
                    $this->line(' - ' . $table);
                }

                return self::FAILURE;
            }

            $this->table(
                ['Source', 'Rows', 'Characters', 'Corporations', 'Types', 'Quantity/Amount', 'First', 'Last'],
                collect($report['sources'])->map(function (array $summary, string $source) {
                    return [
                        $source,
                        $summary['rows'] ?? '-',
                        $summary['characters'] ?? '-',
                        $summary['corporations'] ?? '-',
                        $summary['types'] ?? '-',
                        $summary['quantity'] ?? $summary['amount'] ?? '-',
                        $summary['first_at'] ?? '-',
                        $summary['last_at'] ?? '-',
                    ];
                })->values()->all()
            );

            $this->table(
                ['Integrity check', 'Count'],
                collect($report['integrity'])
                    ->map(fn ($value, string $key) => [$key, $value])
                    ->values()
                    ->all()
            );

            $this->comment('Read-only diagnostics complete. No tax rows were written.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
