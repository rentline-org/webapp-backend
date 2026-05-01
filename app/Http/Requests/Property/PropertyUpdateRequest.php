<?php

namespace App\Http\Requests\Property;

use App\Enums\PropertyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Basic info
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],

            // Location
            'address' => ['sometimes', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:100'],
            'state' => ['sometimes', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'string', 'max:20'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],

            // Property type
            'property_type' => [
                'sometimes',
                Rule::in(array_column(PropertyType::cases(), 'value')),
            ],

            // Flags
            'is_available' => ['sometimes', 'boolean'],
            'is_furnished' => ['sometimes', 'boolean'],
            'is_pet_friendly' => ['sometimes', 'boolean'],

            // Pricing
            'rent_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'buy_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],

            // Property details
            'bedrooms' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'bathrooms' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'square_feet' => ['sometimes', 'nullable', 'numeric', 'min:0'],

            // Arrays
            'amenities' => ['sometimes', 'nullable', 'array'],
            'amenities.*' => ['string', 'max:100'],

            'sale_types' => ['sometimes', 'nullable', 'array'],
            'sale_types.*' => ['string', 'max:50'],

            // Dates
            'available_from' => ['sometimes', 'nullable', 'date'],

            // Optional
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['is_available', 'is_furnished', 'is_pet_friendly'] as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->boolean($field);
            }
        }

        $this->merge($data);
    }
}
