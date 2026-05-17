<?php

namespace App\Http\Requests\CustomListing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CustomListingUpdatePropertiesRequest extends FormRequest
{
    /** Determine if the user is authorized to make this request. */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'property_ids' => [
                'sometimes',
                'array',
            ],

            'property_ids.*' => [
                'integer',
                'distinct',
                'exists:properties,id',
            ],
        ];
    }
}
