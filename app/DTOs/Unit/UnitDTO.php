<?php

namespace App\DTOs\Unit;

use App\Enums\UnitType;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitDTO
{
    public function __construct(
        public ?int $property_id,
        public string $name,
        public ?string $description,
        public UnitType $unit_type,
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
    ) {}

    public static function fromRequest(Request $request, ?Unit $existing = null): self
    {
        return new self(
            property_id: $existing?->property_id ?? $request->input('property_id'),

            name: $request->input('name', $existing?->name),
            description: $request->input('description', $existing?->description),

            unit_type: UnitType::from(
                $request->input('unit_type', $existing?->unit_type?->value)
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
        );
    }

    /** Convert DTO → array for model */
    public function toArray(): array
    {
        return [
            'property_id' => $this->property_id,
            'name' => $this->name,
            'description' => $this->description,
            'unit_type' => $this->unit_type->value,
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
        ];
    }
}
