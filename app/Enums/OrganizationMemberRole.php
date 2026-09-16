<?php

namespace App\Enums;

enum OrganizationMemberRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case AGENT = 'agent';
    case TENANT = 'tenant';

    /** @return list<string> */
    public static function operationalRoles(): array
    {
        return [
            self::OWNER->value,
            self::ADMIN->value,
            self::MANAGER->value,
            self::AGENT->value,
        ];
    }

    /** @return list<string> */
    public static function administrativeRoles(): array
    {
        return [
            self::OWNER->value,
            self::ADMIN->value,
            self::MANAGER->value,
        ];
    }
}
