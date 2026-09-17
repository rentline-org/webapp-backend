<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Services\Organization\ActiveOrganizationContext;
use App\Services\Organization\AssignedPropertyAccess;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->activeOrganizationId() !== null
            && ($this->canOperate($user) || $user->membershipRole() !== null);
    }

    public function view(User $user, Document $document): bool
    {
        if (! $this->belongsToActiveOrganization($document)) {
            return false;
        }

        if ($this->canOperate($user)) {
            return app(AssignedPropertyAccess::class)->canAccessDocument($user, $document);
        }

        return $document->shares()
            ->active()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        return $this->canOperate($user) && $this->activeOrganizationId() !== null;
    }

    public function update(User $user, Document $document): bool
    {
        return $this->canOperate($user)
            && $this->belongsToActiveOrganization($document)
            && app(AssignedPropertyAccess::class)->canAccessDocument($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }

    public function restore(User $user, Document $document): bool
    {
        return false;
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return false;
    }

    private function canOperate(User $user): bool
    {
        return $user->canOperateActiveOrganization();
    }

    private function belongsToActiveOrganization(Document $document): bool
    {
        $organizationId = $this->activeOrganizationId();

        return $organizationId !== null && $document->organization_id === $organizationId;
    }

    private function activeOrganizationId(): ?int
    {
        return app(ActiveOrganizationContext::class)->id();
    }
}
