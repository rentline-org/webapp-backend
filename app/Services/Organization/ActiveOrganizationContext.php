<?php

namespace App\Services\Organization;

class ActiveOrganizationContext
{
    public function id(): ?int
    {
        return request()->attributes->get('active_org_id');
    }

    public function hasOrganization(): bool
    {
        return request()->attributes->has('active_org_id');
    }
}
