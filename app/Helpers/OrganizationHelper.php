<?php

namespace App\Helpers;

class OrganizationHelper
{
    protected ?int $organizationId = null;

    public function set(?int $id): void
    {
        $this->organizationId = $id;
    }

    public function get(): ?int
    {
        return $this->organizationId;
    }
}
