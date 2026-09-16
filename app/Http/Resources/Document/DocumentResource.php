<?php

namespace App\Http\Resources\Document;

use App\Enums\DocumentSignerStatus;
use App\Enums\MediaCollection;
use App\Models\DocumentVersion;
use App\Models\Media;
use App\Services\Document\DocumentKindCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canManage = $request->user()?->can('update', $this->resource) ?? false;
        $canDownload = $request->user()?->can('view', $this->resource) ?? false;
        $currentVersion = $this->currentVersion ?? $this->versions?->sortByDesc('version_number')->first();
        $currentSigners = $currentVersion === null
            ? collect()
            : $this->requiredSigners->where('document_version_id', $currentVersion->id)->values();
        $visibleParties = $canManage
            ? $this->parties
            : $this->parties->filter(fn ($party): bool => $party->contact?->user_id === $request->user()?->id)->values();
        $visibleSigners = $canManage
            ? $currentSigners
            : $currentSigners->filter(fn ($signer): bool => $signer->contact?->user_id === $request->user()?->id
                || ($signer->email_snapshot !== null && $signer->email_snapshot === $request->user()?->email))->values();

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'type' => $this->type->value,
            'custom_kind_id' => $this->document_kind_id,
            'kind' => app(DocumentKindCatalog::class)->payloadFor($this->type, $this->customKind),
            'lifecycle' => $this->lifecycle->value,
            'title' => $this->title,
            'purpose' => $this->purpose,
            'description' => $this->description,
            'reference_number' => $this->reference_number,
            'issued_on' => $this->issued_on?->toDateString(),
            'effective_on' => $this->effective_on?->toDateString(),
            'expires_on' => $this->expires_on?->toDateString(),
            'expiry_status' => $this->expiryStatus(),
            'details' => $this->metadata ?? [],
            'supersedes_document_id' => $this->supersedes_document_id,
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
            'contexts' => [
                'properties' => $this->properties->map(fn ($property): array => [
                    'id' => $property->id,
                    'slug' => $property->slug,
                    'title' => $property->title,
                    'relation_type' => $property->pivot->relation_type,
                ])->values(),
                'units' => $this->units->map(fn ($unit): array => [
                    'id' => $unit->id,
                    'property_id' => $unit->property_id,
                    'slug' => $unit->slug,
                    'name' => $unit->name,
                    'relation_type' => $unit->pivot->relation_type,
                ])->values(),
                'leases' => $this->leases->map(fn ($lease): array => [
                    'id' => $lease->id,
                    'title' => $lease->title,
                    'workflow_status' => $lease->workflow_status?->value,
                    'starts_on' => $lease->starts_on?->toDateString(),
                    'ends_on' => $lease->ends_on?->toDateString(),
                    'relation_type' => $lease->pivot->relation_type,
                ])->values(),
            ],
            'parties' => $visibleParties->map(fn ($party): array => [
                'id' => $party->id,
                'contact_id' => $party->contact_id,
                'name' => $party->contact?->name ?? $party->name_snapshot,
                'role' => $party->role,
                'is_primary' => $party->is_primary,
                'ownership_percentage' => $party->ownership_percentage,
            ])->values(),
            'requires_signature' => $this->requires_signature,
            'is_signed' => $this->is_signed,
            'signature_status' => $this->signatureStatus($currentSigners),
            'signed_at' => $this->signed_at?->toIso8601String(),
            'signers' => $visibleSigners->map(fn ($signer): array => [
                'id' => $signer->id,
                'contact_id' => $signer->contact_id,
                'name' => $signer->contact?->name ?? $signer->name_snapshot,
                'email' => $signer->contact?->email ?? $signer->email_snapshot,
                'role' => $signer->role,
                'status' => $signer->status->value,
                'signed_at' => $signer->signed_at?->toIso8601String(),
            ])->values(),
            'uploaded_by' => $this->uploaded_by,
            'uploader' => $this->uploader ? ['id' => $this->uploader->id, 'name' => $this->uploader->name] : null,
            'signed_by' => $this->signed_by,
            'signer' => $this->signer ? ['id' => $this->signer->id, 'name' => $this->signer->name] : null,
            'lease' => $this->lease ? LeaseResource::make($this->lease) : null,
            'files' => $this->versionFiles($currentVersion, $canManage),
            'current_version' => $currentVersion ? $this->versionPayload($currentVersion, $canManage) : null,
            'versions' => $this->versions->map(fn (DocumentVersion $version): array => $this->versionPayload($version, $canManage))->values(),
            'shares' => $canManage
                ? $this->shares->whereNull('revoked_at')->map(fn ($share): array => [
                    'id' => $share->id,
                    'contact_id' => $share->contact_id,
                    'user' => $share->user ? ['id' => $share->user->id, 'name' => $share->user->name, 'email' => $share->user->email] : null,
                    'granted_at' => $share->granted_at?->toIso8601String(),
                ])->values()
                : [],
            'capabilities' => [
                'can_update' => $canManage,
                'can_archive' => $canManage && $this->lifecycle->value !== 'archived',
                'can_manage_signatures' => $canManage,
                'can_share' => $canManage,
                'can_download' => $canDownload,
                'can_create_revision' => $canManage && $this->lifecycle->value !== 'archived',
            ],
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function versionPayload(DocumentVersion $version, bool $canManage): array
    {
        return [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'notes' => $version->notes,
            'creator' => $version->creator ? ['id' => $version->creator->id, 'name' => $version->creator->name] : null,
            'files' => $this->versionFiles($version, $canManage),
            'finalized_at' => $version->finalized_at?->toIso8601String(),
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function versionFiles(?DocumentVersion $version, bool $canManage): array
    {
        if ($version === null) {
            return [
                'original' => $this->filePayload($this->getLastMedia(MediaCollection::DOCUMENT_ORIGINAL->value), 'original'),
                'signed' => $this->filePayload($this->getLastMedia(MediaCollection::DOCUMENT_SIGNED->value), 'signed'),
                'supporting' => [],
            ];
        }

        $supporting = $this->media
            ->where('collection_name', MediaCollection::DOCUMENT_SUPPORTING->value)
            ->filter(fn (Media $media): bool => (int) $media->getCustomProperty('version_id') === $version->id)
            ->filter(fn (Media $media): bool => $canManage || (bool) $media->getCustomProperty('party_visible', false))
            ->map(fn (Media $media): array => $this->filePayload($media, 'supporting', $version))
            ->values();

        return [
            'original' => $this->filePayload($version->primaryMedia, 'original', $version),
            'signed' => $this->filePayload($version->signedMedia, 'signed', $version),
            'supporting' => $supporting,
        ];
    }

    /** @return array<string, mixed>|null */
    private function filePayload(?Media $media, string $variant, ?DocumentVersion $version = null): ?array
    {
        if ($media === null) {
            return null;
        }

        $route = $variant === 'supporting'
            ? route('documents.versions.supporting.download', [
                'document' => $this->id,
                'version' => $version?->id,
                'media' => $media->id,
            ], false)
            : ($version === null
                ? route('documents.files.download', ['document' => $this->id, 'variant' => $variant], false)
                : route('documents.versions.files.download', [
                    'document' => $this->id,
                    'version' => $version->id,
                    'variant' => $variant,
                ], false));

        return [
            'id' => $media->id,
            'name' => $media->name,
            'label' => $media->getCustomProperty('label'),
            'file_name' => $media->getCustomProperty('original_name', $media->file_name),
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'sha256' => $media->getCustomProperty('sha256'),
            'party_visible' => (bool) $media->getCustomProperty('party_visible', false),
            'download_url' => $route,
        ];
    }

    private function expiryStatus(): ?string
    {
        if ($this->expires_on === null) {
            return null;
        }

        if ($this->expires_on->isPast() && ! $this->expires_on->isToday()) {
            return 'expired';
        }

        return $this->expires_on->lte(today()->addDays(30)) ? 'expiring' : 'current';
    }

    private function signatureStatus($signers): string
    {
        if (! $this->requires_signature) {
            return 'not_required';
        }
        if ($this->is_signed) {
            return 'signed';
        }
        if ($signers->contains(fn ($signer): bool => $signer->status === DocumentSignerStatus::DECLINED)) {
            return 'declined';
        }

        return 'pending';
    }
}
