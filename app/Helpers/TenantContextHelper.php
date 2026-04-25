<?php

namespace App\Helpers;

class TenantContextHelper
{
    public static function id(): ?int
    {
        return request()?->attributes?->get('active_organization_id');
    }
}
