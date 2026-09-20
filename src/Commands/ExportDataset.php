<?php

namespace DeirdreLear\Seat\Taxes\Commands;

use Carbon\CarbonImmutable;
use DeirdreLear\Seat\Taxes\Services\Dataset\DatasetExporter;
use Illuminate\Console\Command;
use Throwable;

class ExportDataset extends Command
{
    protected $signature = 'taxes:dataset:export
        {from : First UTC date, YYYY-MM-DD}
        {to : Last UTC date, YYYY-MM-DD}
        {--alliance=* : Alliance ID to include; repeat for multiple alliances}
        {--holding-corp=* : Moon observer owner corporation ID; repeat as needed}
        {--mineral-region=* : Limit ordinary mining to these region IDs}
        {--wallet-ref-type=* : Override exported wallet ref types}
        {--output= : Output directory; defaults under storage/app/seat-taxes-datasets}';

    protected $description =
        'Export a sanitized, scoped production dataset for SeAT Taxes testing';

    public function handle(DatasetExporter $exporter): int
    {
        try {
            $from = $this->parseDate((string) $this->argument('from'));
            $to = $this->parseDate((string) $this->argument('to'));

            $alliances = $this->ids($this->option('alliance'));

            if ($alliances === []) {
                $this->error(
                    'At least one --alliance=<id> is required. '
                    . 'The exporter intentionally refuses an unbounded production dump.'
                );

                return self::FAILURE;
            }

            $this->warn(
                'The export contains wallet and mining history. '
                . 'Authentication secrets are excluded, but the dataset is still confidential.'
            );

            $this->line('Period: ' . $from->toDateString() . ' .. ' . $to->toDateString() . ' UTC');
            $this->line('Alliances: ' . implode(', ', $alliances));
            $this->newLine();

            $result = $exporter->export(
                from: $from,
                to: $to,
                allianceIds: $alliances,
                holdingCorporationIds: $this->ids($this->option('holding-corp')),
                mineralRegionIds: $this->ids($this->option('mineral-region')),
                walletRefTypes: array_values(array_filter(array_map(
                    'trim',
                    $this->option('wallet-ref-type')
                ))),
                outputDirectory: $this->option('output')
                    ? (string) $this->option('output')
                    : null,
            );

            $manifest = $result['manifest'];

            $this->info('Dataset export completed.');
            $this->line('Directory: ' . $result['directory']);
            $this->newLine();

            $this->table(
                ['File', 'Rows', 'Compressed', 'SHA-256 (first 16)'],
                collect($manifest['files'])
                    ->map(function (array $file, string $name) {
                        return [
                            $file['file'],
                            number_format((int) $file['rows']),
                            $this->humanBytes((int) $file['compressed_bytes']),
                            substr($file['sha256_uncompressed_jsonl'], 0, 16),
                        ];
                    })
                    ->values()
                    ->all()
            );

            $this->newLine();
            $this->line('OAuth/access tokens exported: NO');
            $this->line('Passwords/email addresses exported: NO');
            $this->line('Manifest: ' . $result['directory'] . DIRECTORY_SEPARATOR . 'manifest.json');

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
            throw new \InvalidArgumentException(
                "Invalid date '{$value}'. Use YYYY-MM-DD."
            );
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

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB'];
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return sprintf(
            $unit === 0 ? '%.0f %s' : '%.2f %s',
            $value,
            $units[$unit]
        );
    }
}
