<?php

namespace App\Http\Resources\Organization;

use App\Enums\OrganizationMemberRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = (string) $this->pivot->role;
        $isOwner = $role === OrganizationMemberRole::OWNER->value;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'locale' => $this->preferredLocale(),
            'role' => $role,
            'status' => (string) $this->pivot->status,
            'invited_by' => $this->pivot->invited_by,
            'accepted_at' => $this->pivot->accepted_at,
            'joined_at' => $this->pivot->created_at,
            'capabilities' => [
                'can_update' => ! $isOwner && ($request->user()?->canManageActiveOrganization() ?? false),
                'can_remove' => ! $isOwner && ($request->user()?->canManageActiveOrganization() ?? false),
            ],
        ];
    }
}
