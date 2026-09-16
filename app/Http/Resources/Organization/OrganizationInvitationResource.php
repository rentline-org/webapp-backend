<?php

namespace App\Http\Resources\Organization;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationInvitationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canManage = $request->user()?->canManageActiveOrganization($this->organization_id) ?? false;

        return [
            'id' => $this->id,
            'organization' => [
                'id' => $this->organization_id,
                'title' => $this->whenLoaded('organization', fn () => $this->organization->title),
            ],
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
            ] : null),
            'inviter' => $this->whenLoaded('inviter', fn () => [
                'id' => $this->inviter->id,
                'name' => $this->inviter->name,
            ]),
            'email' => $this->email,
            'role' => $this->role->value,
            'locale' => $this->locale,
            'status' => $this->status(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'capabilities' => [
                'can_resend' => $canManage && $this->accepted_at === null && $this->revoked_at === null,
                'can_revoke' => $canManage && $this->accepted_at === null && $this->revoked_at === null,
            ],
        ];
    }
}
