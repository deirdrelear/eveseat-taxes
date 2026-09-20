<?php

namespace DeirdreLear\Seat\Taxes\Services\Sources;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DeirdreLear\Seat\Taxes\Contracts\ReadOnlySourceResolver;
use DeirdreLear\Seat\Taxes\Data\SourceRecord;
use DeirdreLear\Seat\Taxes\Support\DateWindow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Seat\Eveapi\Models\Industry\CorporationIndustryMiningObserverData;

class MoonMiningResolver implements ReadOnlySourceResolver
{
    public function source(): string
    {
        return 'moon_mining';
    }

    public function recordsForDate(CarbonInterface $date): LazyCollection
    {
        [$start, $end] = DateWindow::utcDay($date);

        return CorporationIndustryMiningObserverData::query()
            ->whereBetween('last_updated', [$start, $end])
            ->orderBy('id')
            ->cursor()
            ->map(function ($row) {
                return new SourceRecord(
                    source: $this->source(),
                    sourceKey: 'observer:' . $row->id,
                    occurredAt: CarbonImmutable::parse($row->last_updated, 'UTC'),
                    characterId: (int) $row->character_id,
                    corporationId: (int) $row->recorded_corporation_id,
                    typeId: (int) $row->type_id,
                    quantity: $row->quantity,
                    payload: [
                        'observer_id' => (int) $row->observer_id,
                        'observer_owner_corporation_id' => (int) $row->corporation_id,
                        'recorded_corporation_id' => (int) $row->recorded_corporation_id,
                    ],
                );
            });
    }

    public function summaryForDate(CarbonInterface $date): array
    {
        [$start, $end] = DateWindow::utcDay($date);

        $query = DB::table('corporation_industry_mining_observer_data')
            ->whereBetween('last_updated', [$start, $end]);

        return [
            'rows' => (clone $query)->count(),
            'characters' => (clone $query)->distinct()->count('character_id'),
            'corporations' => (clone $query)->distinct()->count('recorded_corporation_id'),
            'types' => (clone $query)->distinct()->count('type_id'),
            'quantity' => (clone $query)->sum('quantity'),
            'first_at' => (clone $query)->min('last_updated'),
            'last_at' => (clone $query)->max('last_updated'),
        ];
    }
}
