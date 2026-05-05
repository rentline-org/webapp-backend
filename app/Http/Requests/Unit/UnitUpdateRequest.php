<?php

namespace App\Http\Requests\Unit;

use App\Enums\UnitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Basic info
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],

            // Type
            'unit_type' => [
                'sometimes',
                Rule::in(array_column(UnitType::cases(), 'value')),
            ],

            // Flags
            'is_available' => ['sometimes', 'boolean'],
            'is_furnished' => ['sometimes', 'boolean'],
            'is_pet_friendly' => ['sometimes', 'boolean'],

            // Pricing
            'rent_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sale_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],

            // Details
            'bedrooms' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'bathrooms' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'square_feet' => ['sometimes', 'nullable', 'numeric', 'min:0'],

            // Arrays
            'amenities' => ['sometimes', 'nullable', 'array'],
            'amenities.*' => ['string', 'max:100'],

            // Dates
            'available_from' => ['sometimes', 'nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_available' => $this->boolean('is_available'),
            'is_furnished' => $this->boolean('is_furnished'),
            'is_pet_friendly' => $this->boolean('is_pet_friendly'),
        ]);
    }
}
