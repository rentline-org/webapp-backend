<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Services\Organization\ActiveOrganizationContext;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user) && $this->activeOrganizationId() !== null;
    }

    public function view(User $user, Document $document): bool
    {
        return $this->canManage($user) && $this->belongsToActiveOrganization($document);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user) && $this->activeOrganizationId() !== null;
    }

    public function update(User $user, Document $document): bool
    {
        return $this->canManage($user) && $this->belongsToActiveOrganization($document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->canManage($user) && $this->belongsToActiveOrganization($document);
    }

    public function restore(User $user, Document $document): bool
    {
        return false;
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        return $user->isLandlord() || $user->isSuperAdmin();
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
