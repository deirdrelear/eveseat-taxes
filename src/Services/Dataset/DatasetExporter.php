<?php

namespace DeirdreLear\Seat\Taxes\Services\Dataset;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Composer\InstalledVersions;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class DatasetExporter
{
    public const FORMAT_VERSION = 1;

    private const REQUIRED_TABLES = [
        'corporation_industry_mining_observer_data',
        'character_minings',
        'corporation_wallet_journals',
        'character_corporation_histories',
        'corporation_alliance_histories',
        'character_affiliations',
        'corporation_infos',
        'refresh_tokens',
        'users',
        'universe_names',
        'invTypes',
        'invGroups',
        'invTypeMaterials',
        'solar_systems',
        'market_prices',
    ];

    public function export(
        CarbonInterface $from,
        CarbonInterface $to,
        array $allianceIds,
        array $holdingCorporationIds = [],
        array $mineralRegionIds = [],
        array $walletRefTypes = [],
        ?string $outputDirectory = null,
    ): array {
        if (! function_exists('gzopen')) {
            throw new RuntimeException('PHP zlib support is required for dataset export.');
        }

        $from = CarbonImmutable::instance($from)->setTimezone('UTC')->startOfDay();
        $to = CarbonImmutable::instance($to)->setTimezone('UTC')->endOfDay();

        if ($to->lt($from)) {
            throw new RuntimeException('Dataset end date must not be earlier than start date.');
        }

        $allianceIds = $this->ids($allianceIds);
        $holdingCorporationIds = $this->ids($holdingCorporationIds);
        $mineralRegionIds = $this->ids($mineralRegionIds);
        $walletRefTypes = array_values(array_unique(array_filter(array_map(
            fn ($value) => trim((string) $value),
            $walletRefTypes
        ))));

        if ($allianceIds === []) {
            throw new RuntimeException(
                'At least one alliance ID is required. Refusing an unbounded production export.'
            );
        }

        if ($walletRefTypes === []) {
            $walletRefTypes = array_values(config('taxes.ratting_ref_types', []));
        }

        $this->assertRequiredTables();

        $directory = $this->prepareOutputDirectory(
            $outputDirectory,
            $from,
            $to
        );

        $manifest = [
            'format' => 'eveseat-taxes-dataset',
            'format_version' => self::FORMAT_VERSION,
            'created_at_utc' => CarbonImmutable::now('UTC')->toIso8601String(),
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'inclusive' => true,
            ],
            'scope' => [
                'alliance_ids' => $allianceIds,
                'holding_corporation_ids' => $holdingCorporationIds,
                'mineral_region_ids' => $mineralRegionIds,
                'wallet_ref_types' => $walletRefTypes,
                'wallet_divisions' => array_values(config('taxes.wallet_divisions', [1])),
            ],
            'source' => [
                'database_driver' => DB::connection()->getDriverName(),
                'seat_eveapi_version' => $this->packageVersion('eveseat/eveapi'),
                'seat_services_version' => $this->packageVersion('eveseat/services'),
                'seat_web_version' => $this->packageVersion('eveseat/web'),
                'plugin_version' => $this->packageVersion('deirdrelear/eveseat-taxes'),
            ],
            'security' => [
                'contains_oauth_tokens' => false,
                'contains_access_tokens' => false,
                'contains_passwords' => false,
                'contains_email_addresses' => false,
                'contains_wallet_and_mining_history' => true,
                'note' => 'Treat this dataset as confidential even though authentication secrets are excluded.',
            ],
            'files' => [],
            'counts' => [],
        ];

        try {
            $candidateCorporationIds = $this->candidateCorporations(
                $allianceIds,
                $to
            );

            if ($candidateCorporationIds === []) {
                throw new RuntimeException(
                    'No corporations were found for the requested alliance scope.'
                );
            }

            $candidateCharacterIds = $this->candidateCharacters(
                $candidateCorporationIds,
                $to
            );

            $sourceCharacterIds = [];
            $sourceCorporationIds = [];
            $sourceTypeIds = [];
            $sourceSolarSystemIds = [];
            $universeEntityIds = array_fill_keys($allianceIds, true);

            $moonQuery = DB::table('corporation_industry_mining_observer_data')
                ->select($this->existingColumns(
                    'corporation_industry_mining_observer_data',
                    [
                        'id',
                        'corporation_id',
                        'observer_id',
                        'recorded_corporation_id',
                        'character_id',
                        'type_id',
                        'last_updated',
                        'quantity',
                        'created_at',
                        'updated_at',
                    ]
                ))
                ->whereBetween('last_updated', [$from, $to]);

            $this->whereInChunks(
                $moonQuery,
                'recorded_corporation_id',
                $candidateCorporationIds
            );

            if ($holdingCorporationIds !== []) {
                $this->whereInChunks(
                    $moonQuery,
                    'corporation_id',
                    $holdingCorporationIds
                );
            }

            $moonQuery
                ->orderBy('last_updated')
                ->orderBy('id');

            $manifest['files']['corporation_industry_mining_observer_data'] =
                $this->writeQuery(
                    $directory,
                    'corporation_industry_mining_observer_data',
                    $moonQuery,
                    function (array $row) use (
                        &$sourceCharacterIds,
                        &$sourceCorporationIds,
                        &$sourceTypeIds,
                        &$universeEntityIds
                    ): void {
                        $this->setId($sourceCharacterIds, $row['character_id'] ?? null);
                        $this->setId($sourceCorporationIds, $row['recorded_corporation_id'] ?? null);
                        $this->setId($sourceCorporationIds, $row['corporation_id'] ?? null);
                        $this->setId($sourceTypeIds, $row['type_id'] ?? null);
                        $this->setId($universeEntityIds, $row['character_id'] ?? null);
                        $this->setId($universeEntityIds, $row['recorded_corporation_id'] ?? null);
                        $this->setId($universeEntityIds, $row['corporation_id'] ?? null);
                    }
                );

            $characterMiningQuery = DB::table('character_minings')
                ->select($this->existingColumns(
                    'character_minings',
                    [
                        'id',
                        'character_id',
                        'date',
                        'time',
                        'solar_system_id',
                        'type_id',
                        'quantity',
                        'created_at',
                        'updated_at',
                    ]
                ))
                ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);

            $this->whereInChunks(
                $characterMiningQuery,
                'character_id',
                $candidateCharacterIds
            );

            if ($mineralRegionIds !== []) {
                $allowedSolarSystems = DB::table('solar_systems')
                    ->whereIn('region_id', $mineralRegionIds)
                    ->pluck('system_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $this->whereInChunks(
                    $characterMiningQuery,
                    'solar_system_id',
                    $allowedSolarSystems
                );
            }

            $characterMiningQuery
                ->orderBy('date')
                ->orderBy('time')
                ->orderBy('id');

            $manifest['files']['character_minings'] = $this->writeQuery(
                $directory,
                'character_minings',
                $characterMiningQuery,
                function (array $row) use (
                    &$sourceCharacterIds,
                    &$sourceTypeIds,
                    &$sourceSolarSystemIds,
                    &$universeEntityIds
                ): void {
                    $this->setId($sourceCharacterIds, $row['character_id'] ?? null);
                    $this->setId($sourceTypeIds, $row['type_id'] ?? null);
                    $this->setId($sourceSolarSystemIds, $row['solar_system_id'] ?? null);
                    $this->setId($universeEntityIds, $row['character_id'] ?? null);
                    $this->setId($universeEntityIds, $row['solar_system_id'] ?? null);
                }
            );

            $walletColumns = $this->existingColumns(
                'corporation_wallet_journals',
                [
                    'internal_id',
                    'corporation_id',
                    'division',
                    'id',
                    'date',
                    'ref_type',
                    'amount',
                    'tax_receiver_id',
                    'tax',
                    'first_party_id',
                    'second_party_id',
                    'created_at',
                    'updated_at',
                ]
            );

            $walletQuery = DB::table('corporation_wallet_journals as j')
                ->select(array_map(
                    fn (string $column) => 'j.' . $column,
                    $walletColumns
                ))
                ->whereBetween('j.date', [$from, $to])
                ->whereIn('j.division', array_values(config('taxes.wallet_divisions', [1])))
                ->whereIn('j.ref_type', $walletRefTypes)
                ->whereNotNull('j.second_party_id')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('universe_names as u')
                        ->whereColumn('u.entity_id', 'j.second_party_id')
                        ->where('u.category', 'character');
                });

            $this->whereInChunks(
                $walletQuery,
                'j.corporation_id',
                $candidateCorporationIds
            );

            $walletQuery
                ->orderBy('j.date')
                ->orderBy('j.internal_id');

            $manifest['files']['corporation_wallet_journals'] =
                $this->writeQuery(
                    $directory,
                    'corporation_wallet_journals',
                    $walletQuery,
                    function (array $row) use (
                        &$sourceCharacterIds,
                        &$sourceCorporationIds,
                        &$universeEntityIds
                    ): void {
                        $this->setId($sourceCharacterIds, $row['second_party_id'] ?? null);
                        $this->setId($sourceCorporationIds, $row['corporation_id'] ?? null);
                        $this->setId($universeEntityIds, $row['corporation_id'] ?? null);
                        $this->setId($universeEntityIds, $row['first_party_id'] ?? null);
                        $this->setId($universeEntityIds, $row['second_party_id'] ?? null);
                        $this->setId($universeEntityIds, $row['tax_receiver_id'] ?? null);
                    }
                );

            $sourceCharacterIds = array_keys($sourceCharacterIds);

            if ($sourceCharacterIds === []) {
                throw new RuntimeException(
                    'The selected period/scope contains no tax source characters.'
                );
            }

            $historyCorporationIds = [];
            $characterHistoryQuery = DB::table('character_corporation_histories')
                ->select($this->existingColumns(
                    'character_corporation_histories',
                    [
                        'character_id',
                        'start_date',
                        'corporation_id',
                        'is_deleted',
                        'record_id',
                        'created_at',
                        'updated_at',
                    ]
                ))
                ->where('start_date', '<=', $to);

            $this->whereInChunks(
                $characterHistoryQuery,
                'character_id',
                $sourceCharacterIds
            );

            $characterHistoryQuery
                ->orderBy('character_id')
                ->orderBy('start_date')
                ->orderBy('record_id');

            $manifest['files']['character_corporation_histories'] =
                $this->writeQuery(
                    $directory,
                    'character_corporation_histories',
                    $characterHistoryQuery,
                    function (array $row) use (
                        &$historyCorporationIds,
                        &$universeEntityIds
                    ): void {
                        $this->setId($historyCorporationIds, $row['corporation_id'] ?? null);
                        $this->setId($universeEntityIds, $row['corporation_id'] ?? null);
                    }
                );

            foreach (array_keys($sourceCorporationIds) as $corporationId) {
                $historyCorporationIds[(int) $corporationId] = true;
            }

            $metadataCorporationIds = array_keys($historyCorporationIds);

            $affiliationQuery = DB::table('character_affiliations')
                ->select($this->existingColumns(
                    'character_affiliations',
                    [
                        'character_id',
                        'corporation_id',
                        'alliance_id',
                        'faction_id',
                        'created_at',
                        'updated_at',
                    ]
                ));

            $this->whereInChunks(
                $affiliationQuery,
                'character_id',
                $sourceCharacterIds
            );

            $affiliationQuery->orderBy('character_id');

            $manifest['files']['character_affiliations'] =
                $this->writeQuery(
                    $directory,
                    'character_affiliations',
                    $affiliationQuery,
                    function (array $row) use (
                        &$historyCorporationIds,
                        &$universeEntityIds
                    ): void {
                        $this->setId($historyCorporationIds, $row['corporation_id'] ?? null);
                        $this->setId($universeEntityIds, $row['corporation_id'] ?? null);
                        $this->setId($universeEntityIds, $row['alliance_id'] ?? null);
                    }
                );

            $metadataCorporationIds = array_values(array_unique(array_merge(
                $metadataCorporationIds,
                array_keys($historyCorporationIds)
            )));

            $allianceHistoryIds = [];
            $corporationAllianceHistoryQuery =
                DB::table('corporation_alliance_histories')
                    ->select($this->existingColumns(
                        'corporation_alliance_histories',
                        [
                            'corporation_id',
                            'record_id',
                            'start_date',
                            'alliance_id',
                            'is_deleted',
                            'created_at',
                            'updated_at',
                        ]
                    ))
                    ->where('start_date', '<=', $to);

            $this->whereInChunks(
                $corporationAllianceHistoryQuery,
                'corporation_id',
                $metadataCorporationIds
            );

            $corporationAllianceHistoryQuery
                ->orderBy('corporation_id')
                ->orderBy('start_date')
                ->orderBy('record_id');

            $manifest['files']['corporation_alliance_histories'] =
                $this->writeQuery(
                    $directory,
                    'corporation_alliance_histories',
                    $corporationAllianceHistoryQuery,
                    function (array $row) use (
                        &$allianceHistoryIds,
                        &$universeEntityIds
                    ): void {
                        $this->setId($allianceHistoryIds, $row['alliance_id'] ?? null);
                        $this->setId($universeEntityIds, $row['alliance_id'] ?? null);
                    }
                );

            $corporationInfoColumns = $this->existingColumns(
                'corporation_infos',
                [
                    'corporation_id',
                    'name',
                    'ticker',
                    'member_count',
                    'ceo_id',
                    'alliance_id',
                    'description',
                    'tax_rate',
                    'date_founded',
                    'creator_id',
                    'url',
                    'faction_id',
                    'home_station_id',
                    'shares',
                    'created_at',
                    'updated_at',
                ]
            );

            $corporationInfoQuery = DB::table('corporation_infos')
                ->select($corporationInfoColumns);

            $this->whereInChunks(
                $corporationInfoQuery,
                'corporation_id',
                $metadataCorporationIds
            );

            $corporationInfoQuery->orderBy('corporation_id');

            $manifest['files']['corporation_infos'] = $this->writeQuery(
                $directory,
                'corporation_infos',
                $corporationInfoQuery,
                function (array $row) use (&$universeEntityIds): void {
                    $this->setId($universeEntityIds, $row['corporation_id'] ?? null);
                    $this->setId($universeEntityIds, $row['alliance_id'] ?? null);
                    $this->setId($universeEntityIds, $row['ceo_id'] ?? null);
                    $this->setId($universeEntityIds, $row['creator_id'] ?? null);
                }
            );

            $userIds = [];
            $characterUserMapQuery = DB::table('refresh_tokens')
                ->select($this->existingColumns(
                    'refresh_tokens',
                    [
                        'character_id',
                        'user_id',
                        'deleted_at',
                        'created_at',
                        'updated_at',
                    ]
                ));

            $this->whereInChunks(
                $characterUserMapQuery,
                'character_id',
                $sourceCharacterIds
            );

            $characterUserMapQuery->orderBy('character_id');

            $manifest['files']['character_user_map'] = $this->writeQuery(
                $directory,
                'character_user_map',
                $characterUserMapQuery,
                function (array $row) use (&$userIds): void {
                    $this->setId($userIds, $row['user_id'] ?? null);
                },
                [
                    'logical_projection_of' => 'refresh_tokens',
                    'secrets_removed' => true,
                    'excluded_columns' => [
                        'refresh_token',
                        'token',
                        'character_owner_hash',
                        'scopes',
                        'scopes_profile',
                        'expires_on',
                    ],
                ]
            );

            $userIds = array_keys($userIds);
            $mainCharacterIds = [];

            $usersQuery = DB::table('users')
                ->select($this->existingColumns(
                    'users',
                    ['id', 'name', 'main_character_id']
                ));

            $this->whereInChunks($usersQuery, 'id', $userIds);
            $usersQuery->orderBy('id');

            $manifest['files']['users'] = $this->writeQuery(
                $directory,
                'users',
                $usersQuery,
                function (array $row) use (
                    &$mainCharacterIds,
                    &$universeEntityIds
                ): void {
                    $this->setId($mainCharacterIds, $row['main_character_id'] ?? null);
                    $this->setId($universeEntityIds, $row['main_character_id'] ?? null);
                },
                [
                    'sanitized_projection' => true,
                    'excluded_auth_columns' => true,
                ]
            );

            $materialTypeIds = [];
            $typeMaterialQuery = DB::table('invTypeMaterials')
                ->select($this->existingColumns(
                    'invTypeMaterials',
                    ['typeID', 'materialTypeID', 'quantity']
                ));

            $this->whereInChunks(
                $typeMaterialQuery,
                'typeID',
                array_keys($sourceTypeIds)
            );

            $typeMaterialQuery
                ->orderBy('typeID')
                ->orderBy('materialTypeID');

            $manifest['files']['invTypeMaterials'] = $this->writeQuery(
                $directory,
                'invTypeMaterials',
                $typeMaterialQuery,
                function (array $row) use (&$materialTypeIds): void {
                    $this->setId($materialTypeIds, $row['materialTypeID'] ?? null);
                }
            );

            $allTypeIds = array_values(array_unique(array_merge(
                array_keys($sourceTypeIds),
                array_keys($materialTypeIds)
            )));

            $groupIds = [];
            $invTypesQuery = DB::table('invTypes')
                ->select($this->existingColumns(
                    'invTypes',
                    [
                        'typeID',
                        'groupID',
                        'typeName',
                        'published',
                        'portionSize',
                    ]
                ));

            $this->whereInChunks($invTypesQuery, 'typeID', $allTypeIds);
            $invTypesQuery->orderBy('typeID');

            $manifest['files']['invTypes'] = $this->writeQuery(
                $directory,
                'invTypes',
                $invTypesQuery,
                function (array $row) use (&$groupIds): void {
                    $this->setId($groupIds, $row['groupID'] ?? null);
                }
            );

            $invGroupsQuery = DB::table('invGroups')
                ->select($this->existingColumns(
                    'invGroups',
                    ['groupID', 'categoryID', 'groupName', 'published']
                ));

            $this->whereInChunks(
                $invGroupsQuery,
                'groupID',
                array_keys($groupIds)
            );

            $invGroupsQuery->orderBy('groupID');

            $manifest['files']['invGroups'] = $this->writeQuery(
                $directory,
                'invGroups',
                $invGroupsQuery
            );

            $marketPricesQuery = DB::table('market_prices')
                ->select($this->existingColumns(
                    'market_prices',
                    [
                        'type_id',
                        'average_price',
                        'adjusted_price',
                        'average',
                        'highest',
                        'lowest',
                        'order_count',
                        'volume',
                        'created_at',
                        'updated_at',
                    ]
                ));

            $this->whereInChunks(
                $marketPricesQuery,
                'type_id',
                $allTypeIds
            );

            $marketPricesQuery->orderBy('type_id');

            $manifest['files']['market_prices'] = $this->writeQuery(
                $directory,
                'market_prices',
                $marketPricesQuery
            );

            $solarSystemsQuery = DB::table('solar_systems')
                ->select($this->existingColumns(
                    'solar_systems',
                    [
                        'system_id',
                        'constellation_id',
                        'region_id',
                        'name',
                        'security',
                    ]
                ));

            $this->whereInChunks(
                $solarSystemsQuery,
                'system_id',
                array_keys($sourceSolarSystemIds)
            );

            $solarSystemsQuery->orderBy('system_id');

            $manifest['files']['solar_systems'] = $this->writeQuery(
                $directory,
                'solar_systems',
                $solarSystemsQuery
            );

            $universeNamesQuery = DB::table('universe_names')
                ->select($this->existingColumns(
                    'universe_names',
                    [
                        'entity_id',
                        'name',
                        'category',
                        'created_at',
                        'updated_at',
                    ]
                ));

            $this->whereInChunks(
                $universeNamesQuery,
                'entity_id',
                array_keys($universeEntityIds)
            );

            $universeNamesQuery->orderBy('entity_id');

            $manifest['files']['universe_names'] = $this->writeQuery(
                $directory,
                'universe_names',
                $universeNamesQuery
            );

            foreach ($manifest['files'] as $name => $file) {
                $manifest['counts'][$name] = $file['rows'];
            }

            $manifest['counts']['candidate_corporations'] =
                count($candidateCorporationIds);
            $manifest['counts']['candidate_characters'] =
                count($candidateCharacterIds);
            $manifest['counts']['source_characters'] =
                count($sourceCharacterIds);
            $manifest['counts']['metadata_corporations'] =
                count($metadataCorporationIds);
            $manifest['counts']['source_types'] =
                count($sourceTypeIds);
            $manifest['counts']['material_types'] =
                count($materialTypeIds);

            $manifestPath = $directory . DIRECTORY_SEPARATOR . 'manifest.json';
            $encoded = json_encode(
                $manifest,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            );

            if (file_put_contents($manifestPath, $encoded . PHP_EOL) === false) {
                throw new RuntimeException('Unable to write dataset manifest.');
            }

            @chmod($manifestPath, 0600);

            $readmePath = $directory . DIRECTORY_SEPARATOR . 'README.txt';
            file_put_contents(
                $readmePath,
                "SeAT Taxes test dataset\n"
                . "=======================\n\n"
                . "This export intentionally excludes OAuth/access tokens, passwords and email addresses.\n"
                . "It DOES contain corporation wallet and mining history. Treat it as confidential.\n\n"
                . "Period: {$from->toDateString()} through {$to->toDateString()} UTC (inclusive)\n"
                . "Alliances: " . implode(', ', $allianceIds) . "\n"
                . "Format version: " . self::FORMAT_VERSION . "\n"
            );
            @chmod($readmePath, 0600);

            return [
                'directory' => $directory,
                'manifest' => $manifest,
            ];
        } catch (Throwable $e) {
            $this->writeFailedMarker($directory, $e);
            throw $e;
        }
    }

    private function candidateCorporations(
        array $allianceIds,
        CarbonInterface $to
    ): array {
        $ids = [];

        $historyQuery = DB::table('corporation_alliance_histories')
            ->where('start_date', '<=', $to);

        $this->whereInChunks(
            $historyQuery,
            'alliance_id',
            $allianceIds
        );

        foreach ($historyQuery->distinct()->pluck('corporation_id') as $id) {
            $this->setId($ids, $id);
        }

        $currentQuery = DB::table('corporation_infos');
        $this->whereInChunks(
            $currentQuery,
            'alliance_id',
            $allianceIds
        );

        foreach ($currentQuery->distinct()->pluck('corporation_id') as $id) {
            $this->setId($ids, $id);
        }

        return array_keys($ids);
    }

    private function candidateCharacters(
        array $corporationIds,
        CarbonInterface $to
    ): array {
        $ids = [];

        $historyQuery = DB::table('character_corporation_histories')
            ->where('start_date', '<=', $to);

        $this->whereInChunks(
            $historyQuery,
            'corporation_id',
            $corporationIds
        );

        foreach ($historyQuery->distinct()->pluck('character_id') as $id) {
            $this->setId($ids, $id);
        }

        $currentQuery = DB::table('character_affiliations');
        $this->whereInChunks(
            $currentQuery,
            'corporation_id',
            $corporationIds
        );

        foreach ($currentQuery->distinct()->pluck('character_id') as $id) {
            $this->setId($ids, $id);
        }

        return array_keys($ids);
    }

    private function writeQuery(
        string $directory,
        string $name,
        Builder $query,
        ?callable $observeRow = null,
        array $metadata = [],
    ): array {
        $path = $directory . DIRECTORY_SEPARATOR . $name . '.jsonl.gz';
        $handle = gzopen($path, 'wb9');

        if ($handle === false) {
            throw new RuntimeException("Unable to create {$path}.");
        }

        $hash = hash_init('sha256');
        $rows = 0;
        $columns = null;

        try {
            foreach ($query->cursor() as $record) {
                $row = (array) $record;

                if ($columns === null) {
                    $columns = array_keys($row);
                }

                if ($observeRow !== null) {
                    $observeRow($row);
                }

                $line = json_encode(
                    $row,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_PRESERVE_ZERO_FRACTION
                    | JSON_THROW_ON_ERROR
                ) . "\n";

                if (gzwrite($handle, $line) === false) {
                    throw new RuntimeException("Unable to write {$path}.");
                }

                hash_update($hash, $line);
                $rows++;
            }
        } finally {
            gzclose($handle);
        }

        @chmod($path, 0600);

        return array_merge([
            'file' => basename($path),
            'rows' => $rows,
            'compressed_bytes' => filesize($path) ?: 0,
            'sha256_uncompressed_jsonl' => hash_final($hash),
            'columns' => $columns ?? [],
        ], $metadata);
    }

    private function prepareOutputDirectory(
        ?string $requested,
        CarbonInterface $from,
        CarbonInterface $to
    ): string {
        $directory = $requested;

        if ($directory === null || trim($directory) === '') {
            $directory = storage_path(
                sprintf(
                    'app/seat-taxes-datasets/taxes_%s_%s_%s',
                    $from->format('Ymd'),
                    $to->format('Ymd'),
                    CarbonImmutable::now('UTC')->format('Ymd_His')
                )
            );
        }

        if (! str_starts_with($directory, DIRECTORY_SEPARATOR)) {
            $directory = base_path($directory);
        }

        if (file_exists($directory)) {
            if (! is_dir($directory)) {
                throw new RuntimeException(
                    "Output path exists and is not a directory: {$directory}"
                );
            }

            if ((scandir($directory) ?: []) !== ['.', '..']) {
                throw new RuntimeException(
                    "Output directory is not empty: {$directory}"
                );
            }
        } elseif (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException(
                "Unable to create output directory: {$directory}"
            );
        }

        @chmod($directory, 0700);

        return $directory;
    }

    private function assertRequiredTables(): void
    {
        $missing = [];

        foreach (self::REQUIRED_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Required SeAT tables are missing: ' . implode(', ', $missing)
            );
        }
    }

    private function existingColumns(
        string $table,
        array $requested
    ): array {
        $columns = array_values(array_filter(
            $requested,
            fn (string $column) => Schema::hasColumn($table, $column)
        ));

        if ($columns === []) {
            throw new RuntimeException(
                "None of the requested columns exist in table {$table}."
            );
        }

        return $columns;
    }

    private function whereInChunks(
        Builder $query,
        string $column,
        array $values,
        int $chunkSize = 1000
    ): void {
        $values = array_values(array_unique(array_filter(
            $values,
            fn ($value) => $value !== null && $value !== ''
        )));

        if ($values === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $chunks = array_chunk($values, $chunkSize);

        $query->where(function (Builder $nested) use (
            $column,
            $chunks
        ): void {
            foreach ($chunks as $index => $chunk) {
                if ($index === 0) {
                    $nested->whereIn($column, $chunk);
                } else {
                    $nested->orWhereIn($column, $chunk);
                }
            }
        });
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

    private function setId(array &$set, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $id = (int) $value;

        if ($id > 0) {
            $set[$id] = true;
        }
    }

    private function packageVersion(string $package): ?string
    {
        try {
            if (! class_exists(InstalledVersions::class)) {
                return null;
            }

            return InstalledVersions::isInstalled($package)
                ? InstalledVersions::getPrettyVersion($package)
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function writeFailedMarker(
        string $directory,
        Throwable $error
    ): void {
        try {
            file_put_contents(
                $directory . DIRECTORY_SEPARATOR . 'EXPORT_FAILED.txt',
                $error::class . ': ' . $error->getMessage() . PHP_EOL
            );
        } catch (Throwable) {
            // Never hide the original export exception.
        }
    }
}
