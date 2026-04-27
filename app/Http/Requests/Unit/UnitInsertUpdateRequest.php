<?php

namespace App\Http\Requests\Unit;

use App\Enums\UnitType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitInsertUpdateRequest extends FormRequest
{
    /** Authorization is handled in controller via policies. */
    public function authorize(): bool
    {
        return true;
    }

    /** Validation rules aligned with UnitDTO. */
    public function rules(): array
    {
        return [
            // Basic info
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            // Type
            'unit_type' => [
                'required',
                Rule::in(array_column(UnitType::cases(), 'value')),
            ],

            // Flags
            'is_available' => ['sometimes', 'boolean'],
            'is_furnished' => ['sometimes', 'boolean'],
            'is_pet_friendly' => ['sometimes', 'boolean'],

            // Pricing
            'rent_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],

            // Details
            'bedrooms' => ['nullable', 'integer', 'min:0'],
            'bathrooms' => ['nullable', 'integer', 'min:0'],
            'square_feet' => ['nullable', 'numeric', 'min:0'],

            // Arrays
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'max:100'],

            // Dates
            'available_from' => ['nullable', 'date'],
        ];
    }

    /** Normalize booleans before validation. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_available' => $this->boolean('is_available'),
            'is_furnished' => $this->boolean('is_furnished'),
            'is_pet_friendly' => $this->boolean('is_pet_friendly'),
        ]);
    }
}
