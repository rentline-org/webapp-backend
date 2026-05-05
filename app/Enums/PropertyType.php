<?php

namespace App\Enums;

enum PropertyType: string
{
    case SINGLE_UNIT = 'single_unit';
    case MULTI_UNIT = 'multi_unit';
    case LAND = 'land';

    public function allowedUnitTypes(): array
    {
        return match ($this) {
            self::SINGLE_UNIT => [
                UnitType::HOUSE,
                UnitType::ROOM,
                UnitType::STUDIO,
            ],

            self::MULTI_UNIT => [
                UnitType::APARTMENT,
                UnitType::STUDIO,
                UnitType::ROOM,
            ],

            self::LAND => [
                UnitType::LAND,
            ],
        };
    }

    public function allowedUnitTypeValues(): array
    {
        return array_map(
            fn ($type) => $type->value,
            $this->allowedUnitTypes()
        );
    }
}
