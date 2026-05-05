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

            // Structure only
            'property_type' => [
                'sometimes',
                Rule::in(array_column(PropertyType::cases(), 'value')),
            ],

            // Optional slug update
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
