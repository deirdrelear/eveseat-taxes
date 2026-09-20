<?php

namespace DeirdreLear\Seat\Taxes\Data;

use DeirdreLear\Seat\Taxes\Models\TaxRuleSet;

final class RuleScope
{
    /**
     * @param int[] $allianceIds
     * @param int[] $excludedCorporationIds
     * @param int[] $walletExcludedCorporationIds
     * @param int[] $mineralRegionIds
     * @param int[] $miningHoldingCorporationIds
     */
    public function __construct(
        public array $allianceIds,
        public array $excludedCorporationIds,
        public array $walletExcludedCorporationIds,
        public array $mineralRegionIds,
        public array $miningHoldingCorporationIds,
    ) {
    }

    public static function fromRuleSet(TaxRuleSet $ruleSet): self
    {
        $settings = $ruleSet->settings ?? [];

        return new self(
            allianceIds: self::ids($settings['alliance_ids'] ?? []),
            excludedCorporationIds: self::ids($settings['excluded_corporation_ids'] ?? []),
            walletExcludedCorporationIds: self::ids($settings['wallet_excluded_corporation_ids'] ?? []),
            mineralRegionIds: self::ids($settings['mineral_region_ids'] ?? []),
            miningHoldingCorporationIds: self::ids($settings['mining_holding_corporation_ids'] ?? []),
        );
    }

    public function allowsAlliance(?int $allianceId): bool
    {
        return $allianceId !== null && in_array($allianceId, $this->allianceIds, true);
    }

    public function excludesCorporation(?int $corporationId): bool
    {
        return $corporationId !== null
            && in_array($corporationId, $this->excludedCorporationIds, true);
    }

    public function excludesWalletCorporation(?int $corporationId): bool
    {
        return $corporationId !== null
            && (
                $this->excludesCorporation($corporationId)
                || in_array($corporationId, $this->walletExcludedCorporationIds, true)
            );
    }

    public function allowsMineralRegion(?int $regionId): bool
    {
        return $regionId !== null
            && in_array($regionId, $this->mineralRegionIds, true);
    }

    public function allowsMiningHoldingCorporation(?int $corporationId): bool
    {
        return $corporationId !== null
            && in_array($corporationId, $this->miningHoldingCorporationIds, true);
    }

    private static function ids(array $values): array
    {
        $ids = array_values(array_unique(array_map('intval', $values)));
        sort($ids);

        return $ids;
    }
}
