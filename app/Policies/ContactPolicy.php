<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;
use App\Services\Organization\ActiveOrganizationContext;

class ContactPolicy
{
    /** Determine whether the user can view any models. */
    public function viewAny(User $user): bool
    {
        return $this->canManage($user) && $this->activeOrganizationId() !== null;
    }

    /** Determine whether the user can view the model. */
    public function view(User $user, Contact $contact): bool
    {
        return $this->canManage($user) && $this->belongsToActiveOrganization($contact);
    }

    /** Determine whether the user can create models. */
    public function create(User $user): bool
    {
        return $this->canManage($user) && $this->activeOrganizationId() !== null;
    }

    /** Determine whether the user can update the model. */
    public function update(User $user, Contact $contact): bool
    {
        return $this->canManage($user) && $this->belongsToActiveOrganization($contact);
    }

    /** Determine whether the user can delete the model. */
    public function delete(User $user, Contact $contact): bool
    {
        return $this->canManage($user) && $this->belongsToActiveOrganization($contact);
    }

    /** Determine whether the user can restore the model. */
    public function restore(User $user, Contact $contact): bool
    {
        return false;
    }

    /** Determine whether the user can permanently delete the model. */
    public function forceDelete(User $user, Contact $contact): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        return $user->isLandlord() || $user->isSuperAdmin();
    }

    private function belongsToActiveOrganization(Contact $contact): bool
    {
        $organizationId = $this->activeOrganizationId();

        return $organizationId !== null && $contact->organization_id === $organizationId;
    }

    private function activeOrganizationId(): ?int
    {
        return app(ActiveOrganizationContext::class)->id();
    }
}
