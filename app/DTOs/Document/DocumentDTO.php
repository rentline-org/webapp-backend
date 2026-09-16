<?php

namespace App\DTOs\Document;

use App\Http\Requests\Document\DocumentInsertUpdateRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

final class DocumentDTO
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $leaseAttributes
     * @param  list<UploadedFile>  $supportingFiles
     * @param  list<int>  $propertyIds
     * @param  list<int>  $unitIds
     * @param  list<array<string, mixed>>  $leaseLinks
     * @param  list<array<string, mixed>>  $parties
     * @param  list<array<string, mixed>>  $signers
     * @param  list<string|null>  $supportingLabels
     * @param  list<bool>  $supportingPartyVisibility
     */
    public function __construct(
        public readonly array $attributes,
        public readonly ?array $leaseAttributes,
        public readonly ?UploadedFile $originalFile,
        public readonly ?UploadedFile $signedFile,
        public readonly array $supportingFiles = [],
        public readonly array $propertyIds = [],
        public readonly array $unitIds = [],
        public readonly array $leaseLinks = [],
        public readonly array $parties = [],
        public readonly array $signers = [],
        public readonly array $supportingLabels = [],
        public readonly array $supportingPartyVisibility = [],
        public readonly bool $hasContexts = false,
        public readonly bool $hasParties = false,
        public readonly bool $hasSigners = false,
    ) {}

    public static function fromRequest(DocumentInsertUpdateRequest $request): self
    {
        $validated = $request->validated();
        $attributes = Arr::only($validated, [
            'type',
            'document_kind_id',
            'lifecycle',
            'title',
            'purpose',
            'description',
            'reference_number',
            'issued_on',
            'effective_on',
            'expires_on',
            'supersedes_document_id',
            'property_id',
            'unit_id',
            'requires_signature',
            'is_signed',
        ]);

        if (array_key_exists('details', $validated)) {
            $attributes['metadata'] = $validated['details'];
        }

        foreach (['document_kind_id', 'supersedes_document_id', 'property_id', 'unit_id'] as $field) {
            if (array_key_exists($field, $attributes) && $attributes[$field] !== null) {
                $attributes[$field] = (int) $attributes[$field];
            }
        }

        foreach (['requires_signature', 'is_signed'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = $request->boolean($field);
            }
        }

        $leaseAttributes = array_key_exists('lease', $validated)
            ? $validated['lease']
            : null;

        if ($leaseAttributes !== null && isset($leaseAttributes['tenant_contact_id'])) {
            $leaseAttributes['tenant_contact_id'] = (int) $leaseAttributes['tenant_contact_id'];
        }

        $supportingFiles = array_values(array_filter(
            $request->file('supporting_files', []),
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));

        return new self(
            attributes: $attributes,
            leaseAttributes: $leaseAttributes,
            originalFile: $request->file('file'),
            signedFile: $request->file('signed_file'),
            supportingFiles: $supportingFiles,
            propertyIds: array_values(array_map('intval', $validated['property_ids'] ?? [])),
            unitIds: array_values(array_map('intval', $validated['unit_ids'] ?? [])),
            leaseLinks: array_values($validated['lease_links'] ?? []),
            parties: array_values($validated['parties'] ?? []),
            signers: array_values($validated['signers'] ?? []),
            supportingLabels: array_values($validated['supporting_labels'] ?? []),
            supportingPartyVisibility: array_values(array_map(
                fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOL),
                $validated['supporting_party_visible'] ?? [],
            )),
            hasContexts: array_key_exists('property_ids', $validated)
                || array_key_exists('unit_ids', $validated)
                || array_key_exists('lease_links', $validated),
            hasParties: array_key_exists('parties', $validated),
            hasSigners: array_key_exists('signers', $validated),
        );
    }

    public function hasAttribute(string $attribute): bool
    {
        return array_key_exists($attribute, $this->attributes);
    }
}
