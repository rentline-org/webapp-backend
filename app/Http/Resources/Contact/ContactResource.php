<?php

namespace App\Http\Resources\Contact;

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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
