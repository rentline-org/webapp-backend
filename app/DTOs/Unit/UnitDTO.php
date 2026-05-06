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
        public ?string $slug,
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
            slug: $existing?->slug ?? $request->input('slug'),

            name: $request->input('name', $existing?->name),
            description: $request->input('description', $existing?->description),

            unit_type: UnitType::tryFrom(
                $request->input('unit_type', $existing?->unit_type?->value)
            ) ?? UnitType::APARTMENT, // safe fallback

            is_available: $request->boolean(
                'is_available',
                $existing?->is_available ?? true
            ),

            is_furnished: $request->boolean(
                'is_furnished',
                $existing?->is_furnished ?? false
            ),

            rent_price: self::nullableFloat(
                $request->input('rent_price', $existing?->rent_price)
            ),

            sale_price: self::nullableFloat(
                $request->input('sale_price', $existing?->sale_price)
            ),

            bedrooms: self::nullableInt(
                $request->input('bedrooms', $existing?->bedrooms)
            ),

            bathrooms: self::nullableInt(
                $request->input('bathrooms', $existing?->bathrooms)
            ),

            square_feet: self::nullableFloat(
                $request->input('square_feet', $existing?->square_feet)
            ),

            amenities: $request->input('amenities', $existing?->amenities),

            available_from: $request->input(
                'available_from',
                $existing?->available_from
            ),

            is_pet_friendly: $request->boolean(
                'is_pet_friendly',
                $existing?->is_pet_friendly ?? false
            ),
        );
    }

    public static function fromArray(array $data, ?Unit $existing = null): self
    {
        return new self(
            property_id: $data['property_id'] ?? null,
            slug: $existing?->slug ?? ($data['slug'] ?? null),
            name: $data['name'],
            description: $data['description'] ?? null,

            unit_type: UnitType::from($data['unit_type']),

            is_available: $data['is_available'] ?? true,
            is_furnished: $data['is_furnished'] ?? false,
            is_pet_friendly: $data['is_pet_friendly'] ?? false,

            rent_price: isset($data['rent_price'])
                ? self::nullableFloat($data['rent_price'])
                : null,

            sale_price: isset($data['sale_price'])
                ? self::nullableFloat($data['sale_price'])
                : null,

            bedrooms: isset($data['bedrooms'])
                ? self::nullableInt($data['bedrooms'])
                : null,

            bathrooms: isset($data['bathrooms'])
                ? self::nullableInt($data['bathrooms'])
                : null,

            square_feet: isset($data['square_feet'])
                ? self::nullableFloat($data['square_feet'])
                : null,

            amenities: $data['amenities'] ?? null,

            available_from: $data['available_from'] ?? null,

        );
    }

    /** Convert DTO → array for model */
    public function toArray(): array
    {
        return [
            'property_id' => $this->property_id,
            'name' => $this->name,
            'description' => $this->description,
            'slug' => $this->slug,
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

    private static function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /** Normalize nullable int inputs */
    private static function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
