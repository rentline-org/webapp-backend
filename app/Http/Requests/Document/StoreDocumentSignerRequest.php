<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentSignerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document && ($this->user()?->can('update', $document) ?? false);
    }

    public function rules(): array
    {
        $organizationId = app(ActiveOrganizationContext::class)->id();

        return [
            'contact_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('contacts', 'id')->where('organization_id', $organizationId),
            ],
            'name' => ['required_without:contact_id', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'role' => ['sometimes', 'string', 'max:50'],
        ];
    }
}
