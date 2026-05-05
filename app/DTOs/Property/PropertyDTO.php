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
        );
    }

    public static function fromArray(array $data, ?Property $existing = null): self
    {
        return new self(
            organization_id: $existing?->organization_id,
            slug: $existing?->slug ?? ($data['slug'] ?? null),

            title: $data['title'],
            description: $data['description'] ?? null,

            address: $data['address'],
            city: $data['city'],
            state: $data['state'],
            postal_code: $data['postal_code'],
            country: $data['country'] ?? null,

            property_type: PropertyType::from($data['property_type']),
        );
    }

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
        ];
    }
}
