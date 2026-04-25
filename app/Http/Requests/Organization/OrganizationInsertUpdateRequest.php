<?php

namespace App\Http\Requests\Organization;

use App\Enums\TaxIDType;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrganizationInsertUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Organization::class);
    }

    public function rules(): array
    {
        $organizationId = $this->route('organization')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            'phone' => ['nullable', 'string', 'max:20'],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('organizations', 'email')->ignore($organizationId),
            ],

            'website' => ['nullable', 'url', 'max:255'],

            // Address
            'country' => ['required', 'string', 'size:2'], // ISO code (BR, DE, etc)
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'address_line' => ['required', 'string', 'max:255'],

            // Tax
            'tax_id' => ['nullable', 'string', 'max:50'],
            'tax_id_type' => [
                'nullable',
                Rule::in(array_column(TaxIDType::cases(), 'value')),
            ],

            // Optional frontend-controlled flags
            'is_active' => ['sometimes', 'boolean'],

            // Media
            'avatar' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
