<?php

namespace App\Http\Requests\Property;

use App\Enums\PropertyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

use function count;
use function is_array;

class PropertyInsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Property core
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],

            'property_type' => [
                'required',
                Rule::in(array_column(PropertyType::cases(), 'value')),
            ],

            'slug' => ['nullable', 'string', 'max:255'],

            'units' => ['required', 'array', 'min:1'],

            'units.*.name' => ['required', 'string', 'max:255'],
            'units.*.description' => ['nullable', 'string'],

            'units.*.unit_type' => ['required', 'string'],

            'units.*.is_available' => ['sometimes', 'boolean'],
            'units.*.is_furnished' => ['sometimes', 'boolean'],
            'units.*.is_pet_friendly' => ['sometimes', 'boolean'],

            'units.*.rent_price' => ['nullable', 'numeric', 'min:0'],
            'units.*.sale_price' => ['nullable', 'numeric', 'min:0'],

            'units.*.bedrooms' => ['nullable', 'integer', 'min:0'],
            'units.*.bathrooms' => ['nullable', 'integer', 'min:0'],
            'units.*.square_feet' => ['nullable', 'numeric', 'min:0'],

            'units.*.available_from' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('property_type');
            $units = $this->input('units', []);

            if (! is_array($units)) {
                return;
            }

            if ($type === PropertyType::SINGLE_UNIT->value) {
                if (count($units) !== 1) {
                    $validator->errors()->add(
                        'units',
                        'Single unit properties must contain exactly one unit.'
                    );
                }
            }

            if ($type === PropertyType::LAND->value) {
                if (count($units) !== 1) {
                    $validator->errors()->add(
                        'units',
                        'Land properties must contain exactly one unit.'
                    );
                }
            }

            // if ($type === PropertyType::MULTI_UNIT->value) {
            //     if (count($units) < 1) {
            //         $validator->errors()->add(
            //             'units',
            //             'Multi-unit properties must contain at least one unit.'
            //         );
            //     }
            // }
        });
    }
}
