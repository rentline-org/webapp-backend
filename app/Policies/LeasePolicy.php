<?php

namespace App\Policies;

use App\Models\Lease;
use App\Models\User;
use App\Services\Organization\ActiveOrganizationContext;
use App\Services\Organization\AssignedPropertyAccess;

class LeasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->canOperate($user) || $user->isTenant();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Lease $lease): bool
    {
        if ($lease->organization_id !== $this->organizationId()) {
            return false;
        }

        if ($this->canOperate($user)) {
            return app(AssignedPropertyAccess::class)->canAccessLease($user, $lease);
        }

        return $lease->parties()
            ->whereHas('contact', fn ($query) => $query->where('user_id', $user->id))
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->canOperate($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Lease $lease): bool
    {
        return $lease->organization_id === $this->organizationId()
            && $this->canOperate($user)
            && app(AssignedPropertyAccess::class)->canAccessLease($user, $lease);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Lease $lease): bool
    {
        return $this->update($user, $lease) && $lease->workflow_status === \App\Enums\LeaseWorkflowStatus::DRAFT;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Lease $lease): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Lease $lease): bool
    {
        return false;
    }

    private function canOperate(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return method_exists($user, 'canOperateActiveOrganization')
            ? $user->canOperateActiveOrganization()
            : $user->isLandlord();
    }

    private function organizationId(): ?int
    {
        return app(ActiveOrganizationContext::class)->id();
    }
}
