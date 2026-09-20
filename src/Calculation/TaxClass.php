<?php

namespace DeirdreLear\Seat\Taxes\Calculation;

final class TaxClass
{
    public const NONE = 'none';
    public const MINERAL = 'mineral';
    public const ICE = 'ice';
    public const R4 = 'R4';
    public const R8 = 'R8';
    public const R16 = 'R16';
    public const R32 = 'R32';
    public const R64 = 'R64';
    public const RATTING = 'ratting';

    public const ASTEROID_CATEGORY_ID = 25;
    public const ICE_GROUP_ID = 465;
    public const R4_GROUP_ID = 1884;
    public const R8_GROUP_ID = 1920;
    public const R16_GROUP_ID = 1921;
    public const R32_GROUP_ID = 1922;
    public const R64_GROUP_ID = 1923;

    public static function fromSde(int $categoryId, int $groupId, bool $published = true): string
    {
        if (! $published || $categoryId !== self::ASTEROID_CATEGORY_ID) {
            return self::NONE;
        }

        return match ($groupId) {
            self::ICE_GROUP_ID => self::ICE,
            self::R4_GROUP_ID => self::R4,
            self::R8_GROUP_ID => self::R8,
            self::R16_GROUP_ID => self::R16,
            self::R32_GROUP_ID => self::R32,
            self::R64_GROUP_ID => self::R64,
            default => self::MINERAL,
        };
    }

    public static function taxableClasses(): array
    {
        return [
            self::MINERAL,
            self::ICE,
            self::R4,
            self::R8,
            self::R16,
            self::R32,
            self::R64,
            self::RATTING,
        ];
    }
}
