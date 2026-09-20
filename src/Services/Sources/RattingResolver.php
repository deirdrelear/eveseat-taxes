<?php

namespace DeirdreLear\Seat\Taxes\Services\Sources;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DeirdreLear\Seat\Taxes\Contracts\ReadOnlySourceResolver;
use DeirdreLear\Seat\Taxes\Data\SourceRecord;
use DeirdreLear\Seat\Taxes\Support\DateWindow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Seat\Eveapi\Models\Wallet\CorporationWalletJournal;

class RattingResolver implements ReadOnlySourceResolver
{
    public function source(): string
    {
        return 'ratting_wallet';
    }

    public function recordsForDate(CarbonInterface $date): LazyCollection
    {
        [$start, $end] = DateWindow::utcDay($date);

        return CorporationWalletJournal::query()
            ->whereBetween('date', [$start, $end])
            ->whereIn('division', config('taxes.wallet_divisions', [1]))
            ->whereIn('ref_type', config('taxes.ratting_ref_types', []))
            ->orderBy('internal_id')
            ->cursor()
            ->map(function ($row) {
                return new SourceRecord(
                    source: $this->source(),
                    sourceKey: sprintf(
                        'wallet:%d:%d:%d',
                        $row->corporation_id,
                        $row->division,
                        $row->id
                    ),
                    occurredAt: CarbonImmutable::parse($row->date, 'UTC'),
                    characterId: $row->second_party_id ? (int) $row->second_party_id : null,
                    corporationId: (int) $row->corporation_id,
                    amount: $row->amount,
                    payload: [
                        'journal_id' => (int) $row->id,
                        'division' => (int) $row->division,
                        'ref_type' => $row->ref_type,
                        'first_party_id' => $row->first_party_id ? (int) $row->first_party_id : null,
                        'second_party_id' => $row->second_party_id ? (int) $row->second_party_id : null,
                        'tax_receiver_id' => $row->tax_receiver_id ? (int) $row->tax_receiver_id : null,
                        'tax' => $row->tax,
                    ],
                );
            });
    }

    public function summaryForDate(CarbonInterface $date): array
    {
        [$start, $end] = DateWindow::utcDay($date);

        $query = DB::table('corporation_wallet_journals')
            ->whereBetween('date', [$start, $end])
            ->whereIn('division', config('taxes.wallet_divisions', [1]))
            ->whereIn('ref_type', config('taxes.ratting_ref_types', []));

        return [
            'rows' => (clone $query)->count(),
            'characters' => (clone $query)->whereNotNull('second_party_id')->distinct()->count('second_party_id'),
            'corporations' => (clone $query)->distinct()->count('corporation_id'),
            'types' => null,
            'amount' => (clone $query)->sum('amount'),
            'first_at' => (clone $query)->min('date'),
            'last_at' => (clone $query)->max('date'),
        ];
    }
}
