<?php

namespace App\Http\Requests\Organization;

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

            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('organizations', 'email')->ignore($organizationId),
            ],

            'website' => ['nullable', 'url', 'max:255'],

            'number_of_properties' => [
                'nullable',
                'integer',
                'min:1',
            ],

            // avatar (Spatie MediaLibrary)
            'avatar' => ['nullable', 'image', 'max:2048'],
            'setActive' => 'boolean',
        ];
    }
}
