<?php

namespace App\Http\Resources\Document;

use App\Enums\MediaCollection;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'type' => $this->type->value,
            'title' => $this->title,
            'purpose' => $this->purpose,
            'description' => $this->description,
            'property_id' => $this->property_id,
            'property' => $this->property ? [
                'id' => $this->property->id,
                'slug' => $this->property->slug,
                'title' => $this->property->title,
            ] : null,
            'unit_id' => $this->unit_id,
            'unit' => $this->unit ? [
                'id' => $this->unit->id,
                'property_id' => $this->unit->property_id,
                'slug' => $this->unit->slug,
                'name' => $this->unit->name,
            ] : null,
            'requires_signature' => $this->requires_signature,
            'is_signed' => $this->is_signed,
            'signature_status' => ! $this->requires_signature
                ? 'not_required'
                : ($this->is_signed ? 'signed' : 'pending'),
            'signed_at' => $this->signed_at?->toIso8601String(),
            'uploaded_by' => $this->uploaded_by,
            'uploader' => $this->uploader ? [
                'id' => $this->uploader->id,
                'name' => $this->uploader->name,
            ] : null,
            'signed_by' => $this->signed_by,
            'signer' => $this->signer ? [
                'id' => $this->signer->id,
                'name' => $this->signer->name,
            ] : null,
            'lease' => $this->lease ? LeaseResource::make($this->lease) : null,
            'files' => [
                'original' => $this->filePayload(
                    $this->getFirstMedia(MediaCollection::DOCUMENT_ORIGINAL->value),
                    'original'
                ),
                'signed' => $this->filePayload(
                    $this->getFirstMedia(MediaCollection::DOCUMENT_SIGNED->value),
                    'signed'
                ),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function filePayload(?Media $media, string $variant): ?array
    {
        if ($media === null) {
            return null;
        }

        return [
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->getCustomProperty('original_name', $media->file_name),
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'download_url' => route(
                'documents.files.download',
                ['document' => $this->id, 'variant' => $variant],
                false
            ),
        ];
    }
}
