<?php

use App\Enums\PropertyType;
use App\Enums\UnitType;

class PropertyUnitTypeMap
{
    public static function allowed(PropertyType $type): array
    {
        return match ($type) {
            PropertyType::SINGLE_UNIT => [
                UnitType::HOUSE,
                UnitType::ROOM,
                UnitType::STUDIO,
            ],

            PropertyType::MULTI_UNIT => [
                UnitType::APARTMENT,
                UnitType::STUDIO,
                UnitType::ROOM,
            ],

            PropertyType::LAND => [
                UnitType::LAND,
            ],
        };
    }
}
