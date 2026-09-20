<?php

namespace DeirdreLear\Seat\Taxes\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DeirdreLear\Seat\Taxes\Services\Sources\CharacterMiningResolver;
use DeirdreLear\Seat\Taxes\Services\Sources\MoonMiningResolver;
use DeirdreLear\Seat\Taxes\Services\Sources\RattingResolver;
use DeirdreLear\Seat\Taxes\Support\DateWindow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnosticsService
{
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
        'invTypes',
        'invGroups',
        'invTypeMaterials',
        'market_prices',
    ];

    public function __construct(
        private MoonMiningResolver $moonMining,
        private CharacterMiningResolver $characterMining,
        private RattingResolver $ratting,
    ) {
    }

    public function report(CarbonInterface $date): array
    {
        $day = CarbonImmutable::instance($date)->setTimezone('UTC')->startOfDay();

        $tables = collect(self::REQUIRED_TABLES)
            ->mapWithKeys(fn (string $table) => [$table => Schema::hasTable($table)])
            ->all();

        $missingTables = array_keys(array_filter($tables, fn (bool $exists) => ! $exists));

        if ($missingTables !== []) {
            return [
                'date' => $day->toDateString(),
                'ready' => false,
                'tables' => $tables,
                'missing_tables' => $missingTables,
                'sources' => [],
                'integrity' => [],
            ];
        }

        return [
            'date' => $day->toDateString(),
            'ready' => true,
            'tables' => $tables,
            'missing_tables' => [],
            'sources' => [
                $this->moonMining->source() => $this->moonMining->summaryForDate($day),
                $this->characterMining->source() => $this->characterMining->summaryForDate($day),
                $this->ratting->source() => $this->ratting->summaryForDate($day),
            ],
            'integrity' => $this->integrity($day),
        ];
    }

    private function integrity(CarbonInterface $date): array
    {
        [$start, $end] = DateWindow::utcDay($date);
        $day = CarbonImmutable::instance($date)->setTimezone('UTC')->toDateString();

        $missingMoonTypes = DB::table('corporation_industry_mining_observer_data as m')
            ->leftJoin('invTypes as t', 'm.type_id', '=', 't.typeID')
            ->whereBetween('m.last_updated', [$start, $end])
            ->whereNull('t.typeID')
            ->distinct()
            ->count('m.type_id');

        $missingCharacterMiningTypes = DB::table('character_minings as m')
            ->leftJoin('invTypes as t', 'm.type_id', '=', 't.typeID')
            ->where('m.date', $day)
            ->whereNull('t.typeID')
            ->distinct()
            ->count('m.type_id');

        $unmappedMoonCharacters = DB::table('corporation_industry_mining_observer_data as m')
            ->leftJoin('refresh_tokens as rt', 'm.character_id', '=', 'rt.character_id')
            ->whereBetween('m.last_updated', [$start, $end])
            ->whereNull('rt.user_id')
            ->distinct()
            ->count('m.character_id');

        $unmappedCharacterMiningCharacters = DB::table('character_minings as m')
            ->leftJoin('refresh_tokens as rt', 'm.character_id', '=', 'rt.character_id')
            ->where('m.date', $day)
            ->whereNull('rt.user_id')
            ->distinct()
            ->count('m.character_id');

        $characterMiningWithoutHistoricalCorp = DB::table('character_minings as m')
            ->where('m.date', $day)
            ->whereNotExists(function ($query) use ($end) {
                $query->select(DB::raw(1))
                    ->from('character_corporation_histories as h')
                    ->whereColumn('h.character_id', 'm.character_id')
                    ->where('h.start_date', '<=', $end);
            })
            ->distinct()
            ->count('m.character_id');

        $rawTypesWithoutCurrentPrice = DB::table('character_minings as m')
            ->leftJoin('market_prices as p', 'm.type_id', '=', 'p.type_id')
            ->where('m.date', $day)
            ->whereNull('p.type_id')
            ->distinct()
            ->count('m.type_id');

        return [
            'missing_moon_sde_types' => $missingMoonTypes,
            'missing_character_mining_sde_types' => $missingCharacterMiningTypes,
            'unmapped_moon_characters' => $unmappedMoonCharacters,
            'unmapped_character_mining_characters' => $unmappedCharacterMiningCharacters,
            'character_mining_without_historical_corporation' => $characterMiningWithoutHistoricalCorp,
            'character_mining_raw_types_without_current_price' => $rawTypesWithoutCurrentPrice,
        ];
    }
}
