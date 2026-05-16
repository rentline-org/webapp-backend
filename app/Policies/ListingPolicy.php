<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;
use App\Services\Organization\ActiveOrganizationContext;

class ListingPolicy
{
    /** Determine whether the user can view any models. */
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    /** Determine whether the user can view the model. */
    public function view(User $user, Listing $listing): bool
    {
        return $this->canManage($user) && $this->resolveActiveOrganization($listing);
    }

    /** Determine whether the user can create models. */
    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    /** Determine whether the user can update the model. */
    public function update(User $user, Listing $listing): bool
    {
        return $this->canManage($user) && $this->resolveActiveOrganization($listing);
    }

    /** Determine whether the user can delete the model. */
    public function delete(User $user, Listing $listing): bool
    {
        return false;
    }

    /** Determine whether the user can restore the model. */
    public function restore(User $user, Listing $listing): bool
    {
        return false;
    }

    /** Determine whether the user can permanently delete the model. */
    public function forceDelete(User $user, Listing $listing): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        return $user->isLandlord() || $user->isSuperAdmin();
    }

    private function resolveActiveOrganization(Listing $listing): bool
    {
        $activeOrgContext = app(ActiveOrganizationContext::class);

        return $activeOrgContext->hasOrganization() && $listing->organization()->id == $activeOrgContext->id();
    }
}
