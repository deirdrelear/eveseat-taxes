<?php

namespace DeirdreLear\Seat\Taxes\Data;

use Carbon\CarbonImmutable;
use JsonSerializable;

final class SourceRecord implements JsonSerializable
{
    public function __construct(
        public string $source,
        public string $sourceKey,
        public CarbonImmutable $occurredAt,
        public ?int $characterId = null,
        public ?int $corporationId = null,
        public ?int $typeId = null,
        public int|float|string|null $quantity = null,
        public int|float|string|null $amount = null,
        public array $payload = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'source_key' => $this->sourceKey,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'character_id' => $this->characterId,
            'corporation_id' => $this->corporationId,
            'type_id' => $this->typeId,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
            'payload' => $this->payload,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
