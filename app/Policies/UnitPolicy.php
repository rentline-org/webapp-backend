<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;
use App\Services\Organization\ActiveOrganizationContext;
use App\Services\Organization\AssignedPropertyAccess;

class UnitPolicy
{
    /** Determine whether the user can view any models. */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->canOperateActiveOrganization();
    }

    /** Determine whether the user can view the model. */
    public function view(User $user, Unit $unit): bool
    {
        return $this->canOperate($user, $unit);
    }

    /** Determine whether the user can create models. */
    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->canManageActiveOrganization();
    }

    /** Determine whether the user can update the model. */
    public function update(User $user, Unit $unit): bool
    {
        return $this->canOperate($user, $unit);
    }

    /** Determine whether the user can delete the model. */
    public function delete(User $user, Unit $unit): bool
    {
        return $this->canOperate($user, $unit);
    }

    /** Determine whether the user can restore the model. */
    public function restore(User $user, Unit $unit): bool
    {
        return false;
    }

    /** Determine whether the user can permanently delete the model. */
    public function forceDelete(User $user, Unit $unit): bool
    {
        return $user->isSuperAdmin();
    }

    private function canOperate(User $user, Unit $unit): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $organizationId = app(ActiveOrganizationContext::class)->id();

        return $organizationId !== null
            && $unit->property?->organization_id === $organizationId
            && $user->canOperateActiveOrganization()
            && app(AssignedPropertyAccess::class)->canAccessUnit($user, $organizationId, $unit->id);
    }
}
