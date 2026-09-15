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
     */
    public function __construct(
        public readonly array $attributes,
        public readonly ?array $leaseAttributes,
        public readonly ?UploadedFile $originalFile,
        public readonly ?UploadedFile $signedFile,
    ) {}

    public static function fromRequest(DocumentInsertUpdateRequest $request): self
    {
        $validated = $request->validated();
        $attributes = Arr::only($validated, [
            'type',
            'title',
            'purpose',
            'description',
            'property_id',
            'unit_id',
            'requires_signature',
            'is_signed',
        ]);

        foreach (['property_id', 'unit_id'] as $field) {
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

        if ($leaseAttributes !== null) {
            $leaseAttributes['tenant_contact_id'] = (int) $leaseAttributes['tenant_contact_id'];
        }

        return new self(
            $attributes,
            $leaseAttributes,
            $request->file('file'),
            $request->file('signed_file'),
        );
    }

    public function hasAttribute(string $attribute): bool
    {
        return array_key_exists($attribute, $this->attributes);
    }
}
