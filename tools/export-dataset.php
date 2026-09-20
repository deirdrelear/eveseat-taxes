#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Standalone sanitized dataset exporter for SeAT Taxes.
 *
 * This wrapper intentionally does not require the plugin to be installed in
 * the production SeAT instance. It boots the existing SeAT Laravel app to
 * reuse its DB connection and then loads only DatasetExporter from this repo.
 *
 * Example:
 *
 * php tools/export-dataset.php \
 *   --seat-root=/var/www/seat \
 *   --from=2026-07-01 \
 *   --to=2026-09-19 \
 *   --alliance=99001234 \
 *   --holding-corp=98001234 \
 *   --mineral-region=10000025
 */

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit($code);
}

function values(array $options, string $key): array
{
    if (! array_key_exists($key, $options)) {
        return [];
    }

    $value = $options[$key];

    return is_array($value) ? $value : [$value];
}

function ids(array $values): array
{
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $values),
        fn (int $id) => $id > 0
    )));
    sort($ids);

    return $ids;
}

$options = getopt('', [
    'seat-root:',
    'from:',
    'to:',
    'alliance:',
    'holding-corp:',
    'mineral-region:',
    'wallet-ref-type:',
    'output:',
    'help',
]);

if (isset($options['help'])) {
    echo <<<TXT
SeAT Taxes sanitized dataset exporter

Required:
  --seat-root=/var/www/seat
  --from=YYYY-MM-DD
  --to=YYYY-MM-DD
  --alliance=ID                 repeat for multiple alliances

Optional:
  --holding-corp=ID             repeat for multiple moon observer owners
  --mineral-region=ID           repeat for multiple taxable mining regions
  --wallet-ref-type=TYPE        repeat to override legacy wallet ref types
  --output=/path/to/directory

The export contains wallet/mining history but never exports OAuth/access tokens,
passwords or email addresses.

TXT;
    exit(0);
}

$seatRoot = rtrim((string) ($options['seat-root'] ?? ''), DIRECTORY_SEPARATOR);
$fromInput = (string) ($options['from'] ?? '');
$toInput = (string) ($options['to'] ?? '');
$allianceIds = ids(values($options, 'alliance'));

if ($seatRoot === '') {
    fail('--seat-root is required.');
}

if ($fromInput === '' || $toInput === '') {
    fail('--from and --to are required.');
}

if ($allianceIds === []) {
    fail('At least one --alliance=ID is required.');
}

$autoload = $seatRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$bootstrap = $seatRoot . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

if (! is_file($autoload)) {
    fail("SeAT Composer autoload not found: {$autoload}");
}

if (! is_file($bootstrap)) {
    fail("SeAT Laravel bootstrap not found: {$bootstrap}");
}

require $autoload;

$app = require $bootstrap;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$exporterSource = dirname(__DIR__)
    . DIRECTORY_SEPARATOR . 'src'
    . DIRECTORY_SEPARATOR . 'Services'
    . DIRECTORY_SEPARATOR . 'Dataset'
    . DIRECTORY_SEPARATOR . 'DatasetExporter.php';

if (! is_file($exporterSource)) {
    fail("DatasetExporter source not found: {$exporterSource}");
}

require_once $exporterSource;

$parseDate = static function (string $value): Carbon\CarbonImmutable {
    $date = Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');

    if (! $date || $date->format('Y-m-d') !== $value) {
        fail("Invalid date '{$value}'. Use YYYY-MM-DD.");
    }

    return $date;
};

$walletRefTypes = array_values(array_filter(array_map(
    'trim',
    values($options, 'wallet-ref-type')
)));

if ($walletRefTypes === []) {
    $walletRefTypes = [
        'bounty_prizes',
        'ess_escrow_transfer',
        'corporate_reward_payout',
    ];
}

$from = $parseDate($fromInput);
$to = $parseDate($toInput);

fwrite(
    STDOUT,
    "SeAT Taxes sanitized dataset export\n"
    . "SeAT root: {$seatRoot}\n"
    . "Period: {$from->toDateString()} .. {$to->toDateString()} UTC\n"
    . 'Alliances: ' . implode(', ', $allianceIds) . "\n"
    . "OAuth/access tokens will NOT be exported.\n\n"
);

try {
    $exporter = new DeirdreLear\Seat\Taxes\Services\Dataset\DatasetExporter();

    $result = $exporter->export(
        from: $from,
        to: $to,
        allianceIds: $allianceIds,
        holdingCorporationIds: ids(values($options, 'holding-corp')),
        mineralRegionIds: ids(values($options, 'mineral-region')),
        walletRefTypes: $walletRefTypes,
        outputDirectory: isset($options['output'])
            ? (string) $options['output']
            : null,
    );

    echo "DONE\n";
    echo 'Directory: ' . $result['directory'] . "\n";
    echo 'Manifest: ' . $result['directory'] . DIRECTORY_SEPARATOR . "manifest.json\n\n";

    echo str_pad('File', 52)
        . str_pad('Rows', 14, ' ', STR_PAD_LEFT)
        . str_pad('Compressed', 16, ' ', STR_PAD_LEFT)
        . "\n";
    echo str_repeat('-', 82) . "\n";

    foreach ($result['manifest']['files'] as $file) {
        $bytes = (int) $file['compressed_bytes'];
        $size = $bytes >= 1024 * 1024
            ? number_format($bytes / 1024 / 1024, 2) . ' MiB'
            : number_format($bytes / 1024, 2) . ' KiB';

        echo str_pad((string) $file['file'], 52)
            . str_pad(number_format((int) $file['rows']), 14, ' ', STR_PAD_LEFT)
            . str_pad($size, 16, ' ', STR_PAD_LEFT)
            . "\n";
    }

    echo "\nTreat the resulting directory as confidential: it contains wallet/mining history.\n";
    exit(0);
} catch (Throwable $e) {
    fail($e::class . ': ' . $e->getMessage());
}
