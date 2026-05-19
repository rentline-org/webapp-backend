<?php

namespace App\Http\Requests\CustomListing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomListingCreateRequest extends FormRequest
{
    /** Determine if the user is authorized to make this request. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:255',
                'alpha_dash',

                Rule::unique('custom_listings', 'subdomain'),
            ],

            'headline' => [
                'string',
                'max:255',
            ],

            'is_published' => [
                'sometimes',
                'boolean',
            ],

            'use_organization_defaults' => [
                'sometimes',
                'boolean',
            ],

            'show_contact_form' => [
                'sometimes',
                'boolean',
            ],

            'show_phone' => [
                'sometimes',
                'boolean',
            ],

            'show_email' => [
                'sometimes',
                'boolean',
            ],

            'contact_email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'contact_phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'languages' => [
                'nullable',
                'array',
            ],

            'languages.*' => [
                'string',
                'max:10',
            ],

            'property_ids' => [
                'nullable',
                'array',
            ],

            'property_ids.*' => [
                'integer',
                'distinct',
                'exists:properties,id',
            ],
        ];
    }


    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domain.unique' => 'This domain is already in use.',
            'property_ids.*.exists' => 'One or more selected properties are invalid.',
        ];
    }
}
