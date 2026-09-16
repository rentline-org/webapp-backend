<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentShareRequest extends FormRequest
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
            'user_id' => [
                'required', 'integer',
                Rule::exists('organization_user', 'user_id')->where('organization_id', $organizationId),
            ],
            'contact_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('contacts', 'id')->where('organization_id', $organizationId),
            ],
        ];
    }
}
