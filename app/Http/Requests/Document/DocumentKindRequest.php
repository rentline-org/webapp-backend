<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use App\Models\DocumentKind;
use App\Services\Document\DocumentKindCatalog;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentKindRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Document::class) ?? false;
    }

    public function rules(): array
    {
        $organizationId = app(ActiveOrganizationContext::class)->id();
        $kind = $this->route('documentKind');

        return [
            'key' => [
                $kind instanceof DocumentKind ? 'sometimes' : 'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/',
                Rule::unique('document_kinds', 'key')
                    ->where('organization_id', $organizationId)
                    ->ignore($kind),
            ],
            'label_en' => [$kind instanceof DocumentKind ? 'sometimes' : 'required', 'string', 'max:255'],
            'label_pt_br' => [$kind instanceof DocumentKind ? 'sometimes' : 'required', 'string', 'max:255'],
            'category' => ['sometimes', Rule::in(DocumentKindCatalog::CATEGORIES)],
            'allowed_scopes' => [$kind instanceof DocumentKind ? 'sometimes' : 'required', 'array', 'min:1'],
            'allowed_scopes.*' => ['string', 'distinct', Rule::in(DocumentKindCatalog::SCOPES)],
            'supports_expiry' => ['sometimes', 'boolean'],
            'default_requires_signature' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['key', 'label_en', 'label_pt_br'] as $field) {
            if ($this->filled($field)) {
                $normalized[$field] = trim((string) $this->input($field));
            }
        }

        if (isset($normalized['key'])) {
            $normalized['key'] = str($normalized['key'])->lower()->replace('-', '_')->toString();
        }

        $this->merge($normalized);
    }
}
