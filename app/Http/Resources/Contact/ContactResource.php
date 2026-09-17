<?php

namespace App\Http\Resources\Contact;

use App\Http\Resources\Lease\OperationalLeaseResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'type' => $this->type?->value ?? $this->type,
            'identity_kind' => $this->identity_kind?->value ?? $this->identity_kind,
            'preferred_locale' => $this->preferred_locale,
            'user_id' => $this->user_id,
            'tax_id_type' => $this->tax_id_type?->value ?? $this->tax_id_type,
            'tax_id_masked' => $this->maskedTaxId(),
            'property_ids' => $this->whenLoaded(
                'properties',
                fn () => $this->properties->pluck('id')->values()
            ),
            'properties' => $this->whenLoaded(
                'properties',
                fn () => $this->properties->map(fn ($property) => [
                    'id' => $property->id,
                    'slug' => $property->slug,
                    'title' => $property->title,
                ])->values()
            ),
            'assignments' => ContactAssignmentResource::collection(
                $this->whenLoaded('assignments')
            ),
            'leases' => $this->whenLoaded(
                'leaseParties',
                fn () => OperationalLeaseResource::collection(
                    $this->leaseParties->pluck('lease')->filter()->unique('id')->values()
                )
            ),
            'portal_access' => $this->whenLoaded('latestInvitation', fn () => [
                'linked' => $this->user_id !== null,
                'invitation' => $this->latestInvitation === null ? null : [
                    'id' => $this->latestInvitation->id,
                    'email' => $this->latestInvitation->email,
                    'role' => $this->latestInvitation->role->value,
                    'status' => $this->latestInvitation->status(),
                    'expires_at' => $this->latestInvitation->expires_at?->toIso8601String(),
                    'accepted_at' => $this->latestInvitation->accepted_at?->toIso8601String(),
                ],
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
