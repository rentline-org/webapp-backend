<?php

namespace App\Http\Requests\Property;

use App\Enums\PropertyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PropertyInsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Basic info
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            // Location
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],

            // Property type
            'property_type' => [
                'required',
                Rule::in(array_column(PropertyType::cases(), 'value')),
            ],

            // Flags
            'is_available' => ['sometimes', 'boolean'],
            'is_furnished' => ['sometimes', 'boolean'],
            'is_pet_friendly' => ['sometimes', 'boolean'],

            // Pricing
            'rent_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'buy_price' => ['nullable', 'numeric', 'min:0'],

            // Property details
            'bedrooms' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'integer', 'min:0'],
            'square_feet' => ['nullable', 'numeric', 'min:0'],

            // Arrays
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:100'],

            'sale_types' => ['nullable', 'array'],
            'sale_types.*' => ['string', 'max:50'],

            // Dates
            'available_from' => ['nullable', 'date'],

            // Optional
            'slug' => ['nullable', 'string', 'max:255'],
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
