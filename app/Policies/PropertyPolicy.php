<?php

namespace App\Policies;

use App\Helpers\OrganizationHelper;
use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    /**
     * Determine whether the user can view any properties.
     *
     * Landlords and super admins can access the property area.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isLandlord();
    }

    /**
     * Determine whether the user can view a specific property.
     *
     * Super admins can view everything.
     * Landlords can only view properties in their active organization.
     */
    public function view(User $user, Property $property): bool
    {
        return $this->canManage($user, $property);
    }

    /**
     * Determine whether the user can create properties.
     *
     * Only landlords can create properties.
     * Super admins are also allowed as a practical override.
     */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isLandlord();
    }

    /**
     * Determine whether the user can update the property.
     *
     * Landlords can only update properties in their active organization.
     * Super admins can update any property.
     */
    public function update(User $user, Property $property): bool
    {
        return $this->canManage($user, $property);
    }

    /**
     * Determine whether the user can delete the property.
     *
     * Same rules as update.
     */
    public function delete(User $user, Property $property): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->isLandlord()) {
            return false;
        }

        $activeOrgId = app(OrganizationHelper::class)->get();

        return $activeOrgId && $property->organization_id === $activeOrgId;
    }

    /** Restore is only meaningful if you use soft deletes. */
    public function restore(User $user, Property $property): bool
    {
        return $user->isSuperAdmin();
    }

    /** Force delete is only for super admins. */
    public function forceDelete(User $user, Property $property): bool
    {
        return $user->isSuperAdmin();
    }

    private function canManage(User $user, Property $property): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->isLandlord()) {
            return false;
        }

        $activeOrgId = app(OrganizationHelper::class)->get();

        return $activeOrgId && $property->organization_id === $activeOrgId;
    }
}
