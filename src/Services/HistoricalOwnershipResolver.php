<?php

namespace DeirdreLear\Seat\Taxes\Services;

use Carbon\CarbonInterface;
use DeirdreLear\Seat\Taxes\Data\OwnershipSnapshot;
use Seat\Eveapi\Models\Character\CharacterAffiliation;
use Seat\Eveapi\Models\Character\CharacterCorporationHistory;
use Seat\Eveapi\Models\Corporation\CorporationAllianceHistory;
use Seat\Eveapi\Models\Corporation\CorporationInfo;
use Seat\Eveapi\Models\RefreshToken;
use Seat\Web\Models\User;

class HistoricalOwnershipResolver
{
    public function resolve(
        int $characterId,
        CarbonInterface $at,
        ?int $recordedCorporationId = null
    ): OwnershipSnapshot {
        $token = RefreshToken::withTrashed()
            ->where('character_id', $characterId)
            ->first();

        $user = $token?->user_id ? User::find($token->user_id) : null;

        if ($recordedCorporationId !== null) {
            $corporationId = $recordedCorporationId;
            $corporationBasis = 'source_recorded_corporation';
        } else {
            $corporationId = CharacterCorporationHistory::query()
                ->where('character_id', $characterId)
                ->where('start_date', '<=', $at)
                ->orderByDesc('start_date')
                ->orderByDesc('record_id')
                ->value('corporation_id');

            $corporationBasis = $corporationId
                ? 'character_corporation_history'
                : 'current_character_affiliation';

            if (! $corporationId) {
                $corporationId = CharacterAffiliation::query()
                    ->where('character_id', $characterId)
                    ->value('corporation_id');
            }
        }

        $allianceId = null;
        $allianceBasis = 'none';

        if ($corporationId) {
            $allianceId = CorporationAllianceHistory::query()
                ->where('corporation_id', $corporationId)
                ->where('start_date', '<=', $at)
                ->orderByDesc('start_date')
                ->orderByDesc('record_id')
                ->value('alliance_id');

            if ($allianceId !== null) {
                $allianceBasis = 'corporation_alliance_history';
            } else {
                $allianceId = CorporationInfo::query()
                    ->where('corporation_id', $corporationId)
                    ->value('alliance_id');

                $allianceBasis = $allianceId !== null
                    ? 'current_corporation_info'
                    : 'none';
            }
        }

        return new OwnershipSnapshot(
            userId: $user?->id,
            mainCharacterId: $user?->main_character_id,
            corporationId: $corporationId ? (int) $corporationId : null,
            allianceId: $allianceId ? (int) $allianceId : null,
            corporationBasis: $corporationBasis,
            allianceBasis: $allianceBasis,
        );
    }
}
