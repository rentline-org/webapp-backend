<?php

namespace App\DTOs\Property;

use App\Enums\PropertyType;
use App\Models\Property;
use Illuminate\Http\Request;

class PropertyDTO
{
    public function __construct(
        public ?int $organization_id,
        public ?string $slug,
        public string $title,
        public ?string $description,
        public string $address,
        public string $city,
        public string $state,
        public string $postal_code,
        public ?string $country,
        public PropertyType $property_type,
        public bool $is_available,
        public bool $is_furnished,
        public ?float $rent_price,
        public ?float $sale_price,
        public ?int $bedrooms,
        public ?int $bathrooms,
        public ?float $square_feet,
        public ?array $amenities,
        public ?string $available_from,
        public bool $is_pet_friendly,
        public ?array $sale_types,
    ) {}

    public static function fromRequest(Request $request, ?Property $existing = null): self
    {
        return new self(
            organization_id: $existing?->organization_id,
            slug: $existing?->slug ?? $request->input('slug'),

            title: $request->input('title', $existing?->title),
            description: $request->input('description', $existing?->description),

            address: $request->input('address', $existing?->address),
            city: $request->input('city', $existing?->city),
            state: $request->input('state', $existing?->state),
            postal_code: $request->input('postal_code', $existing?->postal_code),
            country: $request->input('country', $existing?->country),

            property_type: PropertyType::from(
                $request->input('property_type', $existing?->property_type?->value)
            ),

            is_available: $request->boolean('is_available', $existing?->is_available ?? true),
            is_furnished: $request->boolean('is_furnished', $existing?->is_furnished ?? false),

            rent_price: $request->input('rent_price', $existing?->rent_price),
            sale_price: $request->input('sale_price', $existing?->sale_price),

            bedrooms: $request->input('bedrooms', $existing?->bedrooms),
            bathrooms: $request->input('bathrooms', $existing?->bathrooms),
            square_feet: $request->input('square_feet', $existing?->square_feet),

            amenities: $request->input('amenities', $existing?->amenities),
            available_from: $request->input('available_from', $existing?->available_from),

            is_pet_friendly: $request->boolean(
                'is_pet_friendly',
                $existing?->is_pet_friendly ?? false
            ),

            sale_types: $request->input('sale_types', $existing?->sale_types),
        );
    }

    /** Convert DTO → array for model */
    public function toArray(): array
    {
        return [
            'organization_id' => $this->organization_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'property_type' => $this->property_type->value,
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
        ];
    }
}
