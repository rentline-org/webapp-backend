<?php

namespace App\Http\Resources\Property;

use App\Http\Resources\Organization\OrganizationResource;
use App\Http\Resources\Unit\UnitResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PropertyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primaryUnit = $this->relationLoaded('units')
            ? $this->units->firstWhere('archived_at', null)
            : $this->primaryUnit();
        $activeUnits = $this->relationLoaded('units')
            ? $this->units->whereNull('archived_at')
            : collect();

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'property_type' => $this->property_type,
            'operational_status' => $this->operational_status?->value ?? $this->operational_status,
            'archived_at' => $this->archived_at,
            'is_available' => $primaryUnit?->isOperationallyAvailable(),
            'is_furnished' => $primaryUnit?->is_furnished,
            'rent_price' => $primaryUnit?->rent_price,
            'sale_price' => $primaryUnit?->sale_price,
            'buy_price' => $primaryUnit?->buy_price,
            'bedrooms' => $primaryUnit?->bedrooms,
            'bathrooms' => $primaryUnit?->bathrooms,
            'square_feet' => $primaryUnit?->square_feet,
            'amenities' => $primaryUnit?->amenities,
            'available_from' => $primaryUnit?->available_from,
            'is_pet_friendly' => $primaryUnit?->is_pet_friendly,
            'sale_types' => $primaryUnit?->sale_types,
            'occupancy_summary' => $this->when($this->relationLoaded('units'), fn () => [
                'vacant' => $activeUnits->filter(fn ($unit) => $unit->occupancyStatus()->value === 'vacant')->count(),
                'reserved' => $activeUnits->filter(fn ($unit) => $unit->occupancyStatus()->value === 'reserved')->count(),
                'occupied' => $activeUnits->filter(fn ($unit) => $unit->occupancyStatus()->value === 'occupied')->count(),
            ]),
            'thumbnail' => $this->thumbnail(),
            'gallery_urls' => $this->gallery(),
            'media' => $this->whenLoaded('media'),
            'longitude' => $this->longitude,
            'latitude' => $this->latitude,
            'full_address' => $this->full_address,
            'region_code' => $this->region_code,
            'address_number' => $this->address_number,

            'organization' => OrganizationResource::make(
                $this->whenLoaded('organization')
            ),
            'units_count' => $this->whenCounted('units'),
            'units' => UnitResource::collection(
                $this->whenLoaded('units')
            ),
            'contact_assignments' => $this->whenLoaded('contactAssignments', fn () => $this->contactAssignments->map(fn ($assignment) => [
                'id' => $assignment->id,
                'contact_id' => $assignment->contact_id,
                'unit_id' => $assignment->unit_id,
                'role' => $assignment->role?->value ?? $assignment->role,
                'source' => $assignment->source?->value ?? $assignment->source,
                'name' => $assignment->contact?->name,
            ])->values()),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
