<?php

namespace App\Http\Resources\Lease;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeasePartyResource extends JsonResource
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
            'contact_id' => $this->contact_id,
            'role' => $this->role?->value ?? $this->role,
            'is_primary' => $this->is_primary,
            'name' => $this->name_snapshot,
            'email' => $this->email_snapshot,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'contact' => $this->whenLoaded('contact', fn () => $this->contact === null ? null : [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'email' => $this->contact->email,
                'identity_kind' => $this->contact->identity_kind?->value ?? $this->contact->identity_kind,
            ]),
        ];
    }
}
