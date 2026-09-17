<?php

namespace App\Http\Requests\Document;

use App\Enums\DocumentLifecycle;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentKind;
use App\Models\Unit;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DocumentInsertUpdateRequest extends FormRequest
{
    /** @var list<string> */
    private const PARTY_ROLES = [
        'owner', 'landlord', 'manager', 'agent', 'broker', 'tenant', 'co_tenant',
        'occupant', 'guarantor', 'vendor', 'insurer', 'inspector', 'witness', 'issuer', 'other',
    ];

    /** @var list<string> */
    private const RELATION_TYPES = ['agreement', 'addendum', 'inspection', 'evidence', 'supporting', 'applies_to'];

    /** @var list<string> */
    private const DETAIL_KEYS = [
        'change_summary', 'management_fee_type', 'management_fee_value', 'exclusive',
        'commission_type', 'commission_value', 'calculation_basis', 'advertising_permitted',
        'creci_reference', 'inspection_type', 'inspected_on', 'outcome', 'provider',
        'policy_number', 'coverage_amount', 'premium_amount', 'deductible_amount', 'currency',
        'service_scope', 'recurring_cost', 'frequency', 'issuer', 'certificate_number',
        'registry_office', 'registration_number', 'acquisition_date',
    ];

    public function authorize(): bool
    {
        $user = $this->user();
        $document = $this->route('document');

        return $user !== null && ($document instanceof Document
            ? $user->can('update', $document)
            : $user->can('create', Document::class));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $document = $this->route('document');
        $creating = ! $document instanceof Document;
        $type = $this->effectiveType();
        $organizationId = app(ActiveOrganizationContext::class)->id();
        $fileRules = $this->fileRules();
        $legacyLeasePayload = $this->exists('lease');

        return [
            'type' => [$creating ? 'required' : 'sometimes', Rule::enum(DocumentType::class)],
            'document_kind_id' => [
                Rule::requiredIf($creating && $type === DocumentType::CUSTOM),
                Rule::prohibitedIf($type !== DocumentType::CUSTOM),
                'integer',
                Rule::exists('document_kinds', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $organizationId)
                    ->where('is_active', true)),
            ],
            'lifecycle' => [
                $creating ? 'sometimes' : 'prohibited',
                Rule::in([DocumentLifecycle::DRAFT->value, DocumentLifecycle::ACTIVE->value]),
            ],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'min:1', 'max:255'],
            'purpose' => [$creating ? 'required' : 'sometimes', 'string', 'min:1', 'max:500'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'reference_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'issued_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'effective_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'expires_on' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:effective_on'],
            'supersedes_document_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('documents', 'id')->where('organization_id', $organizationId),
            ],
            'details' => ['sometimes', 'array:' . implode(',', self::DETAIL_KEYS)],
            'details.change_summary' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'details.management_fee_type' => ['sometimes', Rule::in(['fixed', 'percentage'])],
            'details.management_fee_value' => ['sometimes', 'numeric', 'min:0'],
            'details.exclusive' => ['sometimes', 'boolean'],
            'details.commission_type' => ['sometimes', Rule::in(['fixed', 'percentage'])],
            'details.commission_value' => ['sometimes', 'numeric', 'min:0'],
            'details.calculation_basis' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.advertising_permitted' => ['sometimes', 'boolean'],
            'details.creci_reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'details.inspection_type' => ['sometimes', Rule::in(['move_in', 'move_out', 'routine'])],
            'details.inspected_on' => ['sometimes', 'date_format:Y-m-d'],
            'details.outcome' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'details.provider' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.policy_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.coverage_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'details.premium_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'details.deductible_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'details.currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'details.service_scope' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'details.recurring_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'details.frequency' => ['sometimes', Rule::in(['one_time', 'monthly', 'quarterly', 'yearly'])],
            'details.issuer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.certificate_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.registry_office' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.registration_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'details.acquisition_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'property_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('properties', 'id')->where('organization_id', $organizationId),
            ],
            'unit_id' => ['sometimes', 'nullable', 'integer', Rule::exists('units', 'id')],
            'property_ids' => ['sometimes', 'array', 'max:100'],
            'property_ids.*' => [
                'integer', 'distinct',
                Rule::exists('properties', 'id')->where('organization_id', $organizationId),
            ],
            'unit_ids' => ['sometimes', 'array', 'max:100'],
            'unit_ids.*' => ['integer', 'distinct', Rule::exists('units', 'id')],
            'lease_links' => ['sometimes', 'array', 'max:100'],
            'lease_links.*.lease_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('leases', 'id')->where('organization_id', $organizationId),
            ],
            'lease_links.*.relation_type' => ['sometimes', Rule::in(self::RELATION_TYPES)],
            'parties' => ['sometimes', 'array', 'max:100'],
            'parties.*.contact_id' => [
                'required', 'integer',
                Rule::exists('contacts', 'id')->where('organization_id', $organizationId),
            ],
            'parties.*.role' => ['required', Rule::in(self::PARTY_ROLES)],
            'parties.*.is_primary' => ['sometimes', 'boolean'],
            'parties.*.ownership_percentage' => ['sometimes', 'nullable', 'numeric', 'between:0,100'],
            'signers' => ['sometimes', 'array', 'max:100'],
            'signers.*.contact_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('contacts', 'id')->where('organization_id', $organizationId),
            ],
            'signers.*.name' => ['required_without:signers.*.contact_id', 'string', 'max:255'],
            'signers.*.email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'signers.*.role' => ['sometimes', Rule::in(self::PARTY_ROLES)],
            'requires_signature' => ['sometimes', 'boolean'],
            'is_signed' => [$creating ? 'sometimes' : 'prohibited', 'boolean'],
            'file' => [$creating ? 'required' : 'prohibited', ...$fileRules],
            'signed_file' => [$creating ? 'sometimes' : 'prohibited', ...$fileRules],
            'supporting_files' => [$creating ? 'sometimes' : 'prohibited', 'array', 'max:20'],
            'supporting_files.*' => $fileRules,
            'supporting_labels' => [$creating ? 'sometimes' : 'prohibited', 'array', 'max:20'],
            'supporting_labels.*' => ['nullable', 'string', 'max:255'],
            'supporting_party_visible' => [$creating ? 'sometimes' : 'prohibited', 'array', 'max:20'],
            'supporting_party_visible.*' => ['boolean'],
            'lease' => ['sometimes', 'array'],
            'lease.tenant_contact_id' => [
                Rule::requiredIf($legacyLeasePayload), 'integer',
                Rule::exists('contacts', 'id')->where('organization_id', $organizationId),
            ],
            'lease.starts_on' => [Rule::requiredIf($legacyLeasePayload), 'date_format:Y-m-d'],
            'lease.ends_on' => [Rule::requiredIf($legacyLeasePayload), 'date_format:Y-m-d', 'after:lease.starts_on'],
            'lease.rent_amount' => [Rule::requiredIf($legacyLeasePayload), 'numeric', 'min:0'],
            'lease.security_deposit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lease.notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $document = $this->route('document');
            $type = $this->effectiveType();

            if ($document instanceof Document && $this->exists('type') && $type !== $document->type) {
                $validator->errors()->add('type', 'A document type cannot be changed after it is created.');
            }

            if (
                $document instanceof Document
                && $this->exists('document_kind_id')
                && $this->integer('document_kind_id') !== $document->document_kind_id
            ) {
                $validator->errors()->add('document_kind_id', 'A custom document kind cannot be changed after it is created.');
            }

            if ($this->filled('unit_id')) {
                $propertyId = $this->integer('property_id') ?: $document?->property_id;
                if ($propertyId === null || ! Unit::query()->whereKey($this->integer('unit_id'))->where('property_id', $propertyId)->exists()) {
                    $validator->errors()->add('unit_id', 'The selected unit must belong to the selected property.');
                }
            }

            $isSigned = $this->boolean('is_signed');
            $requiresSignature = $this->exists('requires_signature')
                ? $this->boolean('requires_signature')
                : false;

            if ($isSigned && ! $requiresSignature) {
                $validator->errors()->add('is_signed', 'A document can only be marked signed when a signature is required.');
            }

            if ($isSigned && ! $this->hasFile('signed_file')) {
                $validator->errors()->add('signed_file', 'Upload the signed document before marking it as signed.');
            }

            if ($this->hasFile('signed_file') && ! $isSigned) {
                $validator->errors()->add('is_signed', 'Mark the document as signed when uploading a signed copy.');
            }

            if ($type === DocumentType::INSPECTION_REPORT) {
                foreach (['inspection_type', 'inspected_on'] as $field) {
                    if (! $this->filled("details.{$field}")) {
                        $validator->errors()->add("details.{$field}", 'This field is required for inspection reports.');
                    }
                }
            }

            if ($type === DocumentType::LEASE_ADDENDUM && ! $this->filled('details.change_summary')) {
                $validator->errors()->add('details.change_summary', 'A change summary is required for a lease addendum.');
            }

            if (
                $type === DocumentType::CUSTOM
                && (! $document instanceof Document || $this->exists('document_kind_id'))
                && ! DocumentKind::query()->whereKey($this->integer('document_kind_id'))->exists()
            ) {
                $validator->errors()->add('document_kind_id', 'The selected custom document kind is unavailable.');
            }

            $supportingFileCount = count($this->file('supporting_files', []));
            foreach (['supporting_labels', 'supporting_party_visible'] as $field) {
                if (count($this->input($field, [])) > $supportingFileCount) {
                    $validator->errors()->add($field, 'Supporting file metadata must match an uploaded file.');
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['title', 'purpose', 'description', 'reference_number'] as $field) {
            if ($this->exists($field) && is_string($this->input($field))) {
                $value = trim((string) $this->input($field));
                $normalized[$field] = $value === '' && in_array($field, ['description', 'reference_number'], true)
                    ? null
                    : $value;
            }
        }
        foreach (['document_kind_id', 'property_id', 'unit_id', 'supersedes_document_id'] as $field) {
            if ($this->exists($field) && $this->input($field) === '') {
                $normalized[$field] = null;
            }
        }
        $this->merge($normalized);
    }

    /** @return list<string> */
    private function fileRules(): array
    {
        return [
            'file',
            'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp',
            'extensions:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp',
            'max:10240',
        ];
    }

    private function effectiveType(): ?DocumentType
    {
        $document = $this->route('document');
        $value = $this->input('type', $document instanceof Document ? $document->type->value : null);

        return DocumentType::tryFrom((string) $value);
    }
}
