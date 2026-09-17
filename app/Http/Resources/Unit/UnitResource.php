<?php

namespace App\Http\Resources\Unit;

use App\Http\Resources\Property\PropertyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class UnitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = $this->relationLoaded('property') && $this->property?->relationLoaded('organization')
            ? ($this->property->organization?->timezone ?? config('app.timezone'))
            : config('app.timezone');
        $assignments = $this->contactAssignmentsForPresentation();

        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'unit_type' => $this->unit_type,
            'operational_status' => $this->operational_status?->value ?? $this->operational_status,
            'occupancy_status' => $this->occupancyStatus($timezone)->value,
            'is_available' => $this->isOperationallyAvailable($timezone),
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
            'contact_assignments' => $this->when(
                $assignments !== null,
                fn () => $assignments
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return Collection<int, array<string, mixed>>|null */
    private function contactAssignmentsForPresentation(): ?Collection
    {
        if (! $this->relationLoaded('contactAssignments')) {
            return null;
        }

        $directAssignments = $this->contactAssignments->map(
            fn ($assignment): array => $this->assignmentData(
                $assignment,
                $assignment->source?->value === 'lease' ? 'lease' : 'unit',
            )
        );

        if (! $this->relationLoaded('property') || ! $this->property?->relationLoaded('contactAssignments')) {
            return $directAssignments->values();
        }

        $inheritedAssignments = $this->property->contactAssignments
            ->whereNull('unit_id')
            ->map(fn ($assignment): array => $this->assignmentData($assignment, 'inherited_property'));

        return $directAssignments->concat($inheritedAssignments)->values();
    }

    /** @return array<string, mixed> */
    private function assignmentData(mixed $assignment, string $scope): array
    {
        return [
            'id' => $assignment->id,
            'contact_id' => $assignment->contact_id,
            'property_id' => $assignment->property_id,
            'unit_id' => $assignment->unit_id,
            'lease_id' => $assignment->lease_id,
            'role' => $assignment->role?->value ?? $assignment->role,
            'source' => $assignment->source?->value ?? $assignment->source,
            'scope' => $scope,
            'is_primary' => $assignment->is_primary,
            'starts_on' => $assignment->starts_on?->toDateString(),
            'ends_on' => $assignment->ends_on?->toDateString(),
            'ownership_percentage' => $assignment->ownership_percentage,
            'name' => $assignment->contact?->name,
        ];
    }
}
