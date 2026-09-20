<?php

namespace DeirdreLear\Seat\Taxes\Data;

final class OwnershipSnapshot
{
    public function __construct(
        public ?int $userId,
        public ?int $mainCharacterId,
        public ?int $corporationId,
        public ?int $allianceId,
        public string $corporationBasis,
        public string $allianceBasis,
    ) {
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'main_character_id' => $this->mainCharacterId,
            'corporation_id' => $this->corporationId,
            'alliance_id' => $this->allianceId,
            'corporation_basis' => $this->corporationBasis,
            'alliance_basis' => $this->allianceBasis,
        ];
    }
}
