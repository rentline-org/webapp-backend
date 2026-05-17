<?php

namespace App\Policies;

use App\Models\CustomListing;
use App\Models\User;

class CustomListingPolicy
{
    /** Determine whether the user can view the model. */
    public function view(User $user, CustomListing $customListing): bool
    {
        return $this->canManage($user);
    }

    public function viewPublished(CustomListing $customListing): bool
    {
        return $customListing->is_published;
    }

    /** Determine whether the user can create models. */
    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    /** Determine whether the user can update the model. */
    public function update(User $user, CustomListing $customListing): bool
    {
        return $this->canManage($user);
    }

    /** Determine whether the user can delete the model. */
    public function delete(User $user, CustomListing $customListing): bool
    {
        return $this->canManage($user) || ! $customListing->is_published;
    }

    private function canManage(User $user): bool
    {
        return $user->isLandlord() || $user->isSuperAdmin();
    }
}
