<?php

namespace App\Http\Resources\Contact;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactAssignmentResource extends JsonResource
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
            'property_id' => $this->property_id,
            'unit_id' => $this->unit_id,
            'lease_id' => $this->lease_id,
            'role' => $this->role?->value ?? $this->role,
            'source' => $this->source?->value ?? $this->source,
            'is_primary' => $this->is_primary,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'ownership_percentage' => $this->ownership_percentage,
            'property' => $this->whenLoaded('property', fn () => [
                'id' => $this->property->id,
                'slug' => $this->property->slug,
                'title' => $this->property->title,
            ]),
            'unit' => $this->whenLoaded('unit', fn () => $this->unit === null ? null : [
                'id' => $this->unit->id,
                'slug' => $this->unit->slug,
                'name' => $this->unit->name,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
