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
            'is_available' => $this->is_available,
            'is_furnished' => $this->is_furnished,
            'rent_price' => $this->rent_price,
            'sale_price' => $this->sale_price,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'square_feet' => $this->square_feet,
            'amenities' => $this->amenities,
            'available_from' => $this->available_from,
            'is_pet_friendly' => $this->is_pet_friendly,
            'sale_types' => $this->sale_types,

            'organization' => OrganizationResource::make(
                $this->whenLoaded('organization')
            ),
            'units_count' => $this->whenCounted('units'),
            'units' => UnitResource::collection(
                $this->whenLoaded('units')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
