<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPER_ADMIN = 'Super-Admin';
    case LANDLORD = 'landlord';
    case TENANT = 'tenant';

    public static function fromId(int $id): self
    {
        return match ($id) {
            1 => self::SUPER_ADMIN,
            2 => self::LANDLORD,
            3 => self::TENANT,
            default => throw new \InvalidArgumentException("Invalid role ID: $id"),
        };
    }

    public function id(): int
    {
        return match ($this) {
            self::SUPER_ADMIN => 1,
            self::LANDLORD => 2,
            self::TENANT => 3,
        };
    }
}
