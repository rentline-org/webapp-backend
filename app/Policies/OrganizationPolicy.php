<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isLandlord();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->canAccessOrganization($user, $organization);
    }

    public function create(User $user): bool
    {
        return $user->isLandlord() || $user->isSuperAdmin();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin() || $this->canManageOrganization($user, $organization);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin() || $this->canManageOrganization($user, $organization);
    }

    public function restore(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin() || $this->canManageOrganization($user, $organization);
    }

    public function forceDelete(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin();
    }

    private function canAccessOrganization(User $user, Organization $organization): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $organization->users()->whereKey($user->id)->exists();
    }

    private function canManageOrganization(User $user, Organization $organization): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->isLandlord()) {
            return false;
        }

        return $organization->users()->whereKey($user->id)->exists();
    }
}
