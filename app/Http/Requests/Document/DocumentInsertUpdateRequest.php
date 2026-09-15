<?php

namespace App\Http\Requests\Document;

use App\Enums\ContactPersonType;
use App\Enums\DocumentType;
use App\Enums\MediaCollection;
use App\Models\Document;
use App\Models\Unit;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DocumentInsertUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $document = $this->route('document');

        return $document instanceof Document
            ? $user->can('update', $document)
            : $user->can('create', Document::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = ! $this->route('document') instanceof Document;
        $lease = $this->effectiveType() === DocumentType::LEASE;
        $leasePayloadRequired = $lease && ($creating || $this->exists('lease'));
        $organizationId = app(ActiveOrganizationContext::class)->id();
        $fileRules = [
            'file',
            'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp',
            'extensions:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp',
            'max:10240',
        ];

        return [
            'type' => [
                $creating ? 'required' : 'sometimes',
                Rule::enum(DocumentType::class),
            ],
            'title' => [
                $creating ? 'required' : 'sometimes',
                'string',
                'min:1',
                'max:255',
            ],
            'purpose' => [
                $creating ? 'required' : 'sometimes',
                'string',
                'min:1',
                'max:500',
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'property_id' => [
                Rule::requiredIf($creating && $lease),
                'nullable',
                'integer',
                Rule::exists('properties', 'id')->where(
                    fn ($query) => $query->where('organization_id', $organizationId)
                ),
            ],
            'unit_id' => [
                Rule::requiredIf($creating && $lease),
                'nullable',
                'integer',
                Rule::exists('units', 'id'),
            ],
            'requires_signature' => ['sometimes', 'boolean'],
            'is_signed' => [$creating ? 'sometimes' : 'prohibited', 'boolean'],
            'file' => [$creating ? 'required' : 'prohibited', ...$fileRules],
            'signed_file' => [$creating ? 'sometimes' : 'prohibited', ...$fileRules],
            'lease' => [
                Rule::requiredIf($creating && $lease),
                Rule::prohibitedIf(! $lease),
                'array',
            ],
            'lease.tenant_contact_id' => [
                Rule::requiredIf($leasePayloadRequired),
                'integer',
                Rule::exists('contacts', 'id')->where(
                    fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->where('type', ContactPersonType::TENANT->value)
                ),
            ],
            'lease.starts_on' => [Rule::requiredIf($leasePayloadRequired), 'date_format:Y-m-d'],
            'lease.ends_on' => [
                Rule::requiredIf($leasePayloadRequired),
                'date_format:Y-m-d',
                'after:lease.starts_on',
            ],
            'lease.rent_amount' => [Rule::requiredIf($leasePayloadRequired), 'numeric', 'min:0'],
            'lease.security_deposit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lease.notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $document = $this->route('document');
                $type = $this->effectiveType();

                if (
                    $document instanceof Document
                    && $this->exists('type')
                    && $type !== $document->type
                ) {
                    $validator->errors()->add(
                        'type',
                        'A document type cannot be changed after it is created.'
                    );
                }

                if (
                    $document?->is_signed
                    && ($this->exists('property_id')
                        || $this->exists('unit_id')
                        || $this->exists('lease'))
                ) {
                    $validator->errors()->add(
                        'document',
                        'Remove the signed state before changing agreement context or lease terms.'
                    );
                }

                $requiresSignature = $type === DocumentType::LEASE
                    || ($this->exists('requires_signature')
                        ? $this->boolean('requires_signature')
                        : ($document?->requires_signature ?? false));
                $isSigned = $this->exists('is_signed')
                    ? $this->boolean('is_signed')
                    : ($document?->is_signed ?? false);
                $hasSignedFile = $this->hasFile('signed_file')
                    || ($document?->hasMedia(MediaCollection::DOCUMENT_SIGNED->value) ?? false);

                if (
                    $type === DocumentType::LEASE
                    && $this->exists('requires_signature')
                    && ! $this->boolean('requires_signature')
                ) {
                    $validator->errors()->add(
                        'requires_signature',
                        'Lease documents always require a signature.'
                    );
                }

                if ($isSigned && ! $requiresSignature) {
                    $validator->errors()->add(
                        'is_signed',
                        'A document can only be marked signed when a signature is required.'
                    );
                }

                if ($isSigned && ! $hasSignedFile) {
                    $validator->errors()->add(
                        'signed_file',
                        'Upload the signed document before marking it as signed.'
                    );
                }

                if ($this->hasFile('signed_file') && ! $isSigned) {
                    $validator->errors()->add(
                        'is_signed',
                        'Mark the document as signed when uploading a signed copy.'
                    );
                }

                $propertyId = $this->exists('property_id')
                    ? $this->integer('property_id') ?: null
                    : $document?->property_id;
                $unitId = $this->exists('unit_id')
                    ? $this->integer('unit_id') ?: null
                    : $document?->unit_id;

                if ($unitId !== null && $propertyId === null) {
                    $validator->errors()->add('unit_id', 'Select a property before selecting a unit.');

                    return;
                }

                if (
                    $unitId !== null
                    && ! Unit::query()
                        ->whereKey($unitId)
                        ->where('property_id', $propertyId)
                        ->exists()
                ) {
                    $validator->errors()->add(
                        'unit_id',
                        'The selected unit must belong to the selected property.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['title', 'purpose', 'description'] as $field) {
            if (! $this->exists($field) || ! is_string($this->input($field))) {
                continue;
            }

            $value = trim($this->input($field));
            $normalized[$field] = in_array($field, ['title', 'purpose'], true) || $value !== ''
                ? $value
                : null;
        }

        foreach (['property_id', 'unit_id'] as $field) {
            if ($this->exists($field) && $this->input($field) === '') {
                $normalized[$field] = null;
            }
        }

        $lease = $this->input('lease');
        if (is_array($lease) && array_key_exists('notes', $lease) && is_string($lease['notes'])) {
            $value = trim($lease['notes']);
            $lease['notes'] = $value === '' ? null : $value;
            $normalized['lease'] = $lease;
        }

        $this->merge($normalized);
    }

    private function effectiveType(): ?DocumentType
    {
        $document = $this->route('document');
        $value = $this->input(
            'type',
            $document instanceof Document ? $document->type->value : null
        );

        return DocumentType::tryFrom((string) $value);
    }
}
