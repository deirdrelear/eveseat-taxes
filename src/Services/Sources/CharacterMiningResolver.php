<?php

namespace DeirdreLear\Seat\Taxes\Services\Sources;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DeirdreLear\Seat\Taxes\Contracts\ReadOnlySourceResolver;
use DeirdreLear\Seat\Taxes\Data\SourceRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Seat\Eveapi\Models\Industry\CharacterMining;

class CharacterMiningResolver implements ReadOnlySourceResolver
{
    public function source(): string
    {
        return 'character_mining';
    }

    public function recordsForDate(CarbonInterface $date): LazyCollection
    {
        $day = CarbonImmutable::instance($date)->setTimezone('UTC')->toDateString();

        return CharacterMining::query()
            ->where('date', $day)
            ->orderBy('id')
            ->cursor()
            ->map(function ($row) {
                $occurredAt = CarbonImmutable::parse(
                    sprintf('%s %s', $row->date, $row->time),
                    'UTC'
                );

                return new SourceRecord(
                    source: $this->source(),
                    sourceKey: 'character-mining:' . $row->id,
                    occurredAt: $occurredAt,
                    characterId: (int) $row->character_id,
                    typeId: (int) $row->type_id,
                    quantity: $row->quantity,
                    payload: [
                        'solar_system_id' => (int) $row->solar_system_id,
                        'timestamp_basis' => 'seat_ingestion_delta',
                    ],
                );
            });
    }

    public function summaryForDate(CarbonInterface $date): array
    {
        $day = CarbonImmutable::instance($date)->setTimezone('UTC')->toDateString();

        $query = DB::table('character_minings')->where('date', $day);

        return [
            'rows' => (clone $query)->count(),
            'characters' => (clone $query)->distinct()->count('character_id'),
            'corporations' => null,
            'types' => (clone $query)->distinct()->count('type_id'),
            'quantity' => (clone $query)->sum('quantity'),
            'first_at' => (clone $query)->min('time'),
            'last_at' => (clone $query)->max('time'),
        ];
    }
}
