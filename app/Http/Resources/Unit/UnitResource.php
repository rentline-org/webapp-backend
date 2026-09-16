<?php

namespace App\Http\Resources\Unit;

use App\Http\Resources\Property\PropertyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
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
            'property_id' => $this->property_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'unit_type' => $this->unit_type,
            'operational_status' => $this->operational_status?->value ?? $this->operational_status,
            'occupancy_status' => $this->occupancyStatus()->value,
            'is_available' => $this->isOperationallyAvailable(),
            'archived_at' => $this->archived_at,
            'is_furnished' => $this->is_furnished,
            'rent_price' => $this->rent_price,
            'sale_price' => $this->sale_price,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'square_feet' => $this->square_feet,
            'amenities' => $this->amenities,
            'available_from' => $this->available_from,
            'is_pet_friendly' => $this->is_pet_friendly,
            'thumbnail' => $this->thumbnail(),
            'gallery_urls' => $this->gallery(),
            'media' => $this->whenLoaded('media'),

            'property' => PropertyResource::make(
                $this->whenLoaded('property')
            ),
            'contact_assignments' => $this->whenLoaded('contactAssignments', fn () => $this->contactAssignments->map(fn ($assignment) => [
                'id' => $assignment->id,
                'contact_id' => $assignment->contact_id,
                'role' => $assignment->role?->value ?? $assignment->role,
                'source' => $assignment->source?->value ?? $assignment->source,
                'name' => $assignment->contact?->name,
            ])->values()),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
