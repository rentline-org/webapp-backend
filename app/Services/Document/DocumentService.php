<?php

namespace App\Services\Document;

use App\DTOs\Document\DocumentDTO;
use App\Enums\DocumentLifecycle;
use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentType;
use App\Enums\MediaCollection;
use App\Models\Contact;
use App\Models\Document;
use App\Models\DocumentAuditEvent;
use App\Models\DocumentKind;
use App\Models\DocumentShare;
use App\Models\DocumentSigner;
use App\Models\DocumentVersion;
use App\Models\Lease;
use App\Models\Media;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class DocumentService
{
    public function __construct(
        protected DocumentRepositoryInterface $documentRepository,
        protected ActiveOrganizationContext $activeOrganizationContext,
    ) {}

    public function all(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->documentRepository->paginate(
            $this->activeOrganizationId(),
            $filters,
            min(max($perPage, 1), 100),
        );
    }

    public function load(Document $document): Document
    {
        $this->ensureDocumentBelongsToOrganization($document, $this->activeOrganizationId());

        return $this->documentRepository->load($document);
    }

    public function create(DocumentDTO $dto, int $uploaderId): Document
    {
        if ($dto->originalFile === null) {
            throw ValidationException::withMessages(['file' => ['The original document file is required.']]);
        }

        $createdMedia = [];

        try {
            return DB::transaction(function () use ($dto, $uploaderId, &$createdMedia): Document {
                $organizationId = $this->activeOrganizationId();
                $type = DocumentType::from($dto->attributes['type']);
                $customKind = $this->resolveCustomKind($type, $dto->attributes['document_kind_id'] ?? null, $organizationId);
                $attributes = $this->normalizedPrimaryContext($dto, $organizationId);
                $requiresSignature = (bool) ($attributes['requires_signature']
                    ?? $customKind?->default_requires_signature
                    ?? $this->defaultRequiresSignature($type));
                $isSigned = (bool) ($attributes['is_signed'] ?? false);
                $requiresSignature = $requiresSignature || $dto->signers !== [];

                $this->ensureSignatureStateIsValid($requiresSignature, $isSigned, $dto->signedFile !== null);
                $this->validateContexts($dto, $attributes, $organizationId);

                $legacyLeaseAttributes = $this->legacyLeaseAttributes($dto, $attributes, $organizationId);
                $document = $this->documentRepository->create([
                    ...$attributes,
                    'organization_id' => $organizationId,
                    'uploaded_by' => $uploaderId,
                    'signed_by' => $isSigned ? $uploaderId : null,
                    'type' => $type->value,
                    'document_kind_id' => $customKind?->id,
                    'lifecycle' => $attributes['lifecycle'] ?? DocumentLifecycle::DRAFT->value,
                    'requires_signature' => $requiresSignature,
                    'is_signed' => $isSigned,
                    'signed_at' => $isSigned ? now() : null,
                ], $legacyLeaseAttributes);

                $this->syncContexts($document, $dto, $attributes);
                $this->syncParties($document, $dto->parties, $organizationId);

                $version = $document->versions()->create([
                    'version_number' => 1,
                    'created_by' => $uploaderId,
                    'finalized_at' => $isSigned ? now() : null,
                ]);
                $primaryMedia = $this->addPrivateMedia(
                    $document,
                    $dto->originalFile,
                    MediaCollection::DOCUMENT_ORIGINAL,
                    $uploaderId,
                    $version,
                );
                $createdMedia[] = $primaryMedia;
                $version->update(['primary_media_id' => $primaryMedia->id]);

                if ($dto->signedFile !== null) {
                    $signedMedia = $this->addPrivateMedia(
                        $document,
                        $dto->signedFile,
                        MediaCollection::DOCUMENT_SIGNED,
                        $uploaderId,
                        $version,
                    );
                    $createdMedia[] = $signedMedia;
                    $version->update(['signed_media_id' => $signedMedia->id]);
                }

                foreach ($dto->supportingFiles as $index => $file) {
                    $createdMedia[] = $this->addPrivateMedia(
                        $document,
                        $file,
                        MediaCollection::DOCUMENT_SUPPORTING,
                        $uploaderId,
                        $version,
                        $dto->supportingLabels[$index] ?? null,
                        $dto->supportingPartyVisibility[$index] ?? false,
                    );
                }

                $this->syncSigners($document, $version, $dto->signers, $organizationId, $isSigned, $uploaderId);

                if ($document->lease !== null) {
                    $document->leases()->syncWithoutDetaching([
                        $document->lease->id => ['relation_type' => 'agreement'],
                    ]);
                }

                if ($document->supersedes_document_id !== null && $document->lifecycle === DocumentLifecycle::ACTIVE) {
                    Document::withoutGlobalScopes()
                        ->where('organization_id', $organizationId)
                        ->whereKey($document->supersedes_document_id)
                        ->update(['lifecycle' => DocumentLifecycle::SUPERSEDED->value]);
                }

                $this->audit($document, 'document.created', $uploaderId, [
                    'type' => $type->value,
                    'version' => 1,
                ]);

                return $this->documentRepository->load($document->refresh());
            });
        } catch (Throwable $exception) {
            $this->removeRolledBackMedia($createdMedia);

            throw $exception;
        }
    }

    public function update(Document $document, DocumentDTO $dto, ?int $actorId = null): Document
    {
        return DB::transaction(function () use ($document, $dto, $actorId): Document {
            $organizationId = $this->activeOrganizationId();
            $this->ensureDocumentBelongsToOrganization($document, $organizationId);
            $lockedDocument = Document::withoutGlobalScopes()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if ($lockedDocument->lifecycle === DocumentLifecycle::ARCHIVED) {
                throw new ConflictHttpException('Archived documents cannot be changed.');
            }

            if ($dto->originalFile !== null || $dto->signedFile !== null || $dto->hasAttribute('is_signed')) {
                throw ValidationException::withMessages([
                    'file' => ['Document files and signature state use their dedicated endpoints.'],
                ]);
            }

            if (($lockedDocument->is_signed || $lockedDocument->lifecycle === DocumentLifecycle::ACTIVE)
                && ($dto->hasContexts || $dto->hasParties || $dto->hasSigners || $dto->hasAttribute('metadata'))) {
                throw new ConflictHttpException('Create a revision before changing an active or signed document.');
            }

            $attributes = $this->normalizedPrimaryContext($dto, $organizationId, $lockedDocument);
            $this->validateContexts($dto, $attributes, $organizationId, $lockedDocument);

            unset($attributes['type'], $attributes['is_signed'], $attributes['lifecycle']);

            if ($dto->hasAttribute('requires_signature') && ! $attributes['requires_signature'] && $lockedDocument->is_signed) {
                throw new ConflictHttpException('A signed document cannot disable its signature requirement.');
            }

            $lockedDocument->update($attributes);

            if ($dto->hasContexts || $dto->hasAttribute('property_id') || $dto->hasAttribute('unit_id')) {
                $this->syncContexts($lockedDocument, $dto, $attributes);
            }

            if ($dto->hasParties) {
                $this->syncParties($lockedDocument, $dto->parties, $organizationId);
            }

            if ($dto->hasSigners) {
                $version = $this->ensureCurrentVersion($lockedDocument, $actorId);
                $this->syncSigners($lockedDocument, $version, $dto->signers, $organizationId, false, $actorId);
                $lockedDocument->update(['requires_signature' => $dto->signers !== []]);
            }

            $this->audit($lockedDocument, 'document.updated', $actorId, [
                'fields' => array_keys($attributes),
            ]);

            return $this->documentRepository->load($lockedDocument->refresh());
        });
    }

    /**
     * @param  list<UploadedFile>  $supportingFiles
     * @param  list<string|null>  $supportingLabels
     * @param  list<bool>  $supportingPartyVisibility
     */
    public function createVersion(
        Document $document,
        UploadedFile $file,
        ?UploadedFile $signedFile,
        array $supportingFiles,
        array $supportingLabels,
        array $supportingPartyVisibility,
        ?string $notes,
        int $actorId,
    ): Document {
        $createdMedia = [];

        try {
            return DB::transaction(function () use ($document, $file, $signedFile, $supportingFiles, $supportingLabels, $supportingPartyVisibility, $notes, $actorId, &$createdMedia): Document {
                $organizationId = $this->activeOrganizationId();
                $lockedDocument = Document::withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->whereKey($document->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedDocument->lifecycle === DocumentLifecycle::ARCHIVED) {
                    throw new ConflictHttpException('Archived documents cannot receive new versions.');
                }

                $previousVersion = $this->ensureCurrentVersion($lockedDocument, $actorId);
                $nextNumber = ((int) $lockedDocument->versions()->max('version_number')) + 1;
                $version = $lockedDocument->versions()->create([
                    'version_number' => $nextNumber,
                    'created_by' => $actorId,
                    'notes' => $notes,
                    'finalized_at' => $signedFile !== null ? now() : null,
                ]);

                $primaryMedia = $this->addPrivateMedia(
                    $lockedDocument,
                    $file,
                    MediaCollection::DOCUMENT_ORIGINAL,
                    $actorId,
                    $version,
                );
                $createdMedia[] = $primaryMedia;
                $version->update(['primary_media_id' => $primaryMedia->id]);

                if ($signedFile !== null) {
                    $signedMedia = $this->addPrivateMedia(
                        $lockedDocument,
                        $signedFile,
                        MediaCollection::DOCUMENT_SIGNED,
                        $actorId,
                        $version,
                    );
                    $createdMedia[] = $signedMedia;
                    $version->update(['signed_media_id' => $signedMedia->id]);
                }

                foreach ($supportingFiles as $index => $supportingFile) {
                    $createdMedia[] = $this->addPrivateMedia(
                        $lockedDocument,
                        $supportingFile,
                        MediaCollection::DOCUMENT_SUPPORTING,
                        $actorId,
                        $version,
                        $supportingLabels[$index] ?? null,
                        $supportingPartyVisibility[$index] ?? false,
                    );
                }

                foreach ($previousVersion->signers as $signer) {
                    $version->signers()->create([
                        'document_id' => $lockedDocument->id,
                        'contact_id' => $signer->contact_id,
                        'name_snapshot' => $signer->name_snapshot,
                        'email_snapshot' => $signer->email_snapshot,
                        'role' => $signer->role,
                        'status' => $signedFile === null
                            ? DocumentSignerStatus::PENDING
                            : DocumentSignerStatus::SIGNED,
                        'signed_at' => $signedFile === null ? null : now(),
                        'acted_by' => $signedFile === null ? null : $actorId,
                    ]);
                }

                $lockedDocument->update([
                    'lifecycle' => DocumentLifecycle::DRAFT,
                    'is_signed' => $signedFile !== null,
                    'signed_at' => $signedFile !== null ? now() : null,
                    'signed_by' => $signedFile !== null ? $actorId : null,
                ]);

                $this->audit($lockedDocument, 'document.version_created', $actorId, [
                    'version' => $nextNumber,
                    'previous_version' => $previousVersion->version_number,
                ]);

                return $this->documentRepository->load($lockedDocument->refresh());
            });
        } catch (Throwable $exception) {
            $this->removeRolledBackMedia($createdMedia);

            throw $exception;
        }
    }

    public function markSigned(Document $document, UploadedFile $signedFile, int $signedBy): Document
    {
        $createdMedia = [];

        try {
            return DB::transaction(function () use ($document, $signedFile, $signedBy, &$createdMedia): Document {
                $lockedDocument = $this->lockDocument($document);

                if (! $lockedDocument->requires_signature) {
                    throw ValidationException::withMessages(['signed_file' => ['This document does not require a signature.']]);
                }

                $version = $this->ensureCurrentVersion($lockedDocument, $signedBy);
                if ($version->signed_media_id !== null) {
                    throw new ConflictHttpException('The current version already has a signed copy.');
                }

                $signedMedia = $this->addPrivateMedia(
                    $lockedDocument,
                    $signedFile,
                    MediaCollection::DOCUMENT_SIGNED,
                    $signedBy,
                    $version,
                );
                $createdMedia[] = $signedMedia;
                $version->update(['signed_media_id' => $signedMedia->id, 'finalized_at' => now()]);
                $this->recalculateSignatureState($lockedDocument, $version, $signedBy);
                $this->audit($lockedDocument, 'document.signed_file_uploaded', $signedBy, [
                    'version' => $version->version_number,
                    'media_id' => $signedMedia->id,
                ]);

                return $this->documentRepository->load($lockedDocument->refresh());
            });
        } catch (Throwable $exception) {
            $this->removeRolledBackMedia($createdMedia);

            throw $exception;
        }
    }

    public function markUnsigned(Document $document, ?int $actorId = null): Document
    {
        return DB::transaction(function () use ($document, $actorId): Document {
            $lockedDocument = $this->lockDocument($document);
            if ($lockedDocument->lifecycle !== DocumentLifecycle::DRAFT) {
                throw new ConflictHttpException('Signed files on active documents are immutable.');
            }

            $version = $this->ensureCurrentVersion($lockedDocument, $actorId);
            if ($version->signedMedia !== null) {
                $version->signedMedia->delete();
            }
            $version->update(['signed_media_id' => null, 'finalized_at' => null]);
            $version->signers()->update([
                'status' => DocumentSignerStatus::PENDING->value,
                'signed_at' => null,
                'acted_by' => null,
            ]);
            $lockedDocument->update(['is_signed' => false, 'signed_at' => null, 'signed_by' => null]);
            $this->audit($lockedDocument, 'document.signed_file_removed', $actorId, ['version' => $version->version_number]);

            return $this->documentRepository->load($lockedDocument->refresh());
        });
    }

    /** @param array<string, mixed> $attributes */
    public function addSigner(Document $document, array $attributes, int $actorId): Document
    {
        return DB::transaction(function () use ($document, $attributes, $actorId): Document {
            $lockedDocument = $this->lockMutableDocument($document);
            $version = $this->ensureCurrentVersion($lockedDocument, $actorId);
            $snapshot = $this->signerSnapshot($attributes, $lockedDocument->organization_id);
            $version->signers()->create([
                ...$snapshot,
                'document_id' => $lockedDocument->id,
                'status' => DocumentSignerStatus::PENDING,
            ]);
            $lockedDocument->update(['requires_signature' => true, 'is_signed' => false]);
            $this->audit($lockedDocument, 'document.signer_added', $actorId, ['role' => $snapshot['role']]);

            return $this->documentRepository->load($lockedDocument->refresh());
        });
    }

    public function updateSigner(Document $document, DocumentSigner $signer, DocumentSignerStatus $status, int $actorId): Document
    {
        return DB::transaction(function () use ($document, $signer, $status, $actorId): Document {
            $lockedDocument = $this->lockDocument($document);
            $version = $this->ensureCurrentVersion($lockedDocument, $actorId);

            if ($signer->document_id !== $lockedDocument->id || $signer->document_version_id !== $version->id) {
                throw new AuthorizationException;
            }

            $signer->update([
                'status' => $status,
                'signed_at' => $status === DocumentSignerStatus::SIGNED ? now() : null,
                'acted_by' => $actorId,
            ]);
            $this->recalculateSignatureState($lockedDocument, $version, $actorId);
            $this->audit($lockedDocument, 'document.signer_status_changed', $actorId, [
                'signer_id' => $signer->id,
                'status' => $status->value,
                'version' => $version->version_number,
            ]);

            return $this->documentRepository->load($lockedDocument->refresh());
        });
    }

    public function removeSigner(Document $document, DocumentSigner $signer, int $actorId): Document
    {
        return DB::transaction(function () use ($document, $signer, $actorId): Document {
            $lockedDocument = $this->lockMutableDocument($document);
            $version = $this->ensureCurrentVersion($lockedDocument, $actorId);
            if ($signer->document_id !== $lockedDocument->id || $signer->document_version_id !== $version->id) {
                throw new AuthorizationException;
            }
            $signerId = $signer->id;
            $signer->delete();
            $hasSigners = $version->signers()->exists();
            $lockedDocument->update(['requires_signature' => $hasSigners || $lockedDocument->requires_signature]);
            $this->audit($lockedDocument, 'document.signer_removed', $actorId, ['signer_id' => $signerId]);

            return $this->documentRepository->load($lockedDocument->refresh());
        });
    }

    public function grantShare(Document $document, int $userId, ?int $contactId, int $actorId): Document
    {
        return DB::transaction(function () use ($document, $userId, $contactId, $actorId): Document {
            $lockedDocument = $this->lockDocument($document);
            $organizationId = $lockedDocument->organization_id;
            $user = User::query()
                ->whereKey($userId)
                ->whereHas('organizations', fn ($query) => $query->whereKey($organizationId))
                ->first();

            if ($user === null) {
                throw ValidationException::withMessages(['user_id' => ['The selected user must belong to the active organization.']]);
            }

            $partyContact = $contactId === null
                ? Contact::withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->where('user_id', $userId)
                    ->whereHas('documentParties', fn ($query) => $query->where('document_id', $lockedDocument->id))
                    ->first()
                : Contact::withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->whereKey($contactId)
                    ->whereHas('documentParties', fn ($query) => $query->where('document_id', $lockedDocument->id))
                    ->first();

            if ($partyContact === null || ($partyContact->user_id !== null && $partyContact->user_id !== $userId)) {
                throw ValidationException::withMessages(['contact_id' => ['Shares can only be granted to a linked document party.']]);
            }

            $share = $lockedDocument->shares()->updateOrCreate(
                ['user_id' => $userId],
                [
                    'contact_id' => $partyContact->id,
                    'granted_by' => $actorId,
                    'revoked_by' => null,
                    'granted_at' => now(),
                    'revoked_at' => null,
                ],
            );
            $this->audit($lockedDocument, 'document.share_granted', $actorId, [
                'share_id' => $share->id,
                'user_id' => $userId,
                'contact_id' => $partyContact->id,
            ]);

            return $this->documentRepository->load($lockedDocument->refresh());
        });
    }

    public function revokeShare(Document $document, DocumentShare $share, int $actorId): Document
    {
        return DB::transaction(function () use ($document, $share, $actorId): Document {
            $lockedDocument = $this->lockDocument($document);
            if ($share->document_id !== $lockedDocument->id) {
                throw new AuthorizationException;
            }
            $share->update(['revoked_at' => now(), 'revoked_by' => $actorId]);
            $this->audit($lockedDocument, 'document.share_revoked', $actorId, [
                'share_id' => $share->id,
                'user_id' => $share->user_id,
            ]);

            return $this->documentRepository->load($lockedDocument->refresh());
        });
    }

    public function archive(Document $document, int $actorId): Document
    {
        return DB::transaction(function () use ($document, $actorId): Document {
            $lockedDocument = $this->lockDocument($document);
            $lockedDocument->update(['lifecycle' => DocumentLifecycle::ARCHIVED, 'archived_at' => now()]);
            $this->audit($lockedDocument, 'document.archived', $actorId);

            return $this->documentRepository->load($lockedDocument->refresh());
        });
    }

    public function delete(Document $document, ?int $actorId = null): bool
    {
        $this->ensureDocumentBelongsToOrganization($document, $this->activeOrganizationId());

        if ($document->lifecycle !== DocumentLifecycle::DRAFT || $document->is_signed) {
            $this->archive($document, $actorId ?? (int) auth()->id());

            return true;
        }

        $this->audit($document, 'document.deleted', $actorId);

        return $this->documentRepository->delete($document);
    }

    /** @param array<string, mixed> $metadata */
    public function audit(Document $document, string $event, ?int $actorId, array $metadata = []): DocumentAuditEvent
    {
        return DocumentAuditEvent::query()->create([
            'organization_id' => $document->organization_id,
            'document_id' => $document->id,
            'actor_id' => $actorId,
            'event' => $event,
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => app()->bound('request') ? request()->ip() : null,
        ]);
    }

    private function activeOrganizationId(): int
    {
        return $this->activeOrganizationContext->id()
            ?? throw new AuthorizationException('An active organization is required.');
    }

    private function ensureDocumentBelongsToOrganization(Document $document, int $organizationId): void
    {
        if ($document->organization_id !== $organizationId) {
            throw new AuthorizationException;
        }
    }

    private function resolveCustomKind(DocumentType $type, ?int $kindId, int $organizationId): ?DocumentKind
    {
        if ($type !== DocumentType::CUSTOM) {
            return null;
        }

        $kind = DocumentKind::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->find($kindId);

        if ($kind === null) {
            throw ValidationException::withMessages(['document_kind_id' => ['The selected custom document kind is unavailable.']]);
        }

        return $kind;
    }

    private function defaultRequiresSignature(DocumentType $type): bool
    {
        return in_array($type, [
            DocumentType::LEASE,
            DocumentType::LEASE_ADDENDUM,
            DocumentType::PROPERTY_MANAGEMENT_AGREEMENT,
            DocumentType::BROKERAGE_AUTHORIZATION,
            DocumentType::SERVICE_CONTRACT,
        ], true);
    }

    private function ensureSignatureStateIsValid(bool $requiresSignature, bool $isSigned, bool $hasSignedFile): void
    {
        if ($isSigned && ! $requiresSignature) {
            throw ValidationException::withMessages(['is_signed' => ['A document can only be marked signed when a signature is required.']]);
        }
        if ($isSigned && ! $hasSignedFile) {
            throw ValidationException::withMessages(['signed_file' => ['Upload the signed document before marking it as signed.']]);
        }
        if ($hasSignedFile && ! $isSigned) {
            throw ValidationException::withMessages(['is_signed' => ['Mark the document as signed when uploading a signed copy.']]);
        }
    }

    /** @return array<string, mixed> */
    private function normalizedPrimaryContext(DocumentDTO $dto, int $organizationId, ?Document $document = null): array
    {
        $attributes = $dto->attributes;
        if (! array_key_exists('property_id', $attributes) && $dto->propertyIds !== []) {
            $attributes['property_id'] = $dto->propertyIds[0];
        }
        if (! array_key_exists('unit_id', $attributes) && $dto->unitIds !== []) {
            $attributes['unit_id'] = $dto->unitIds[0];
        }
        if (($attributes['unit_id'] ?? null) !== null && ($attributes['property_id'] ?? $document?->property_id) === null) {
            $attributes['property_id'] = Unit::query()
                ->whereKey($attributes['unit_id'])
                ->whereHas('property', fn ($query) => $query->withoutGlobalScopes()->where('organization_id', $organizationId))
                ->value('property_id');
        }

        return $attributes;
    }

    /** @param array<string, mixed> $attributes */
    private function validateContexts(DocumentDTO $dto, array $attributes, int $organizationId, ?Document $document = null): void
    {
        $propertyIds = array_values(array_unique(array_filter([
            ...$dto->propertyIds,
            $attributes['property_id'] ?? $document?->property_id,
        ])));
        $unitIds = array_values(array_unique(array_filter([
            ...$dto->unitIds,
            $attributes['unit_id'] ?? $document?->unit_id,
        ])));
        $leaseIds = array_values(array_unique(array_map('intval', Arr::pluck($dto->leaseLinks, 'lease_id'))));

        if (Property::withoutGlobalScopes()->where('organization_id', $organizationId)->whereIn('id', $propertyIds)->count() !== count($propertyIds)) {
            throw ValidationException::withMessages(['property_ids' => ['Every selected property must belong to the active organization.']]);
        }
        if (Unit::query()->whereIn('id', $unitIds)->whereHas('property', fn ($query) => $query->withoutGlobalScopes()->where('organization_id', $organizationId))->count() !== count($unitIds)) {
            throw ValidationException::withMessages(['unit_ids' => ['Every selected unit must belong to the active organization.']]);
        }
        if (Lease::withoutGlobalScopes()->where('organization_id', $organizationId)->whereIn('id', $leaseIds)->count() !== count($leaseIds)) {
            throw ValidationException::withMessages(['lease_links' => ['Every selected lease must belong to the active organization.']]);
        }

        $primaryPropertyId = $attributes['property_id'] ?? $document?->property_id;
        $primaryUnitId = $attributes['unit_id'] ?? $document?->unit_id;
        if ($primaryUnitId !== null && ! Unit::query()->whereKey($primaryUnitId)->where('property_id', $primaryPropertyId)->exists()) {
            throw ValidationException::withMessages(['unit_id' => ['The selected unit must belong to the selected property.']]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function syncContexts(Document $document, DocumentDTO $dto, array $attributes): void
    {
        $propertyIds = array_values(array_unique(array_filter([...$dto->propertyIds, $attributes['property_id'] ?? $document->property_id])));
        $unitIds = array_values(array_unique(array_filter([...$dto->unitIds, $attributes['unit_id'] ?? $document->unit_id])));
        $unitPropertyIds = Unit::query()->whereIn('id', $unitIds)->pluck('property_id')->all();
        $propertyIds = array_values(array_unique([...$propertyIds, ...$unitPropertyIds]));

        $document->properties()->sync(collect($propertyIds)->mapWithKeys(
            fn (int $id): array => [$id => ['relation_type' => 'applies_to']]
        )->all());
        $document->units()->sync(collect($unitIds)->mapWithKeys(
            fn (int $id): array => [$id => ['relation_type' => 'applies_to']]
        )->all());
        $document->leases()->sync(collect($dto->leaseLinks)->mapWithKeys(
            fn (array $link): array => [(int) $link['lease_id'] => ['relation_type' => $link['relation_type'] ?? 'supporting']]
        )->all());
    }

    /** @param list<array<string, mixed>> $parties */
    private function syncParties(Document $document, array $parties, int $organizationId): void
    {
        $contactIds = array_values(array_unique(array_map('intval', Arr::pluck($parties, 'contact_id'))));
        $contacts = Contact::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $contactIds)
            ->get()
            ->keyBy('id');

        if ($contacts->count() !== count($contactIds)) {
            throw ValidationException::withMessages(['parties' => ['Every selected party must belong to the active organization.']]);
        }

        $document->parties()->delete();
        foreach ($parties as $party) {
            $contact = $contacts->get((int) $party['contact_id']);
            $document->parties()->create([
                'contact_id' => $contact->id,
                'role' => $party['role'],
                'name_snapshot' => $contact->name,
                'email_snapshot' => $contact->email,
                'is_primary' => filter_var($party['is_primary'] ?? false, FILTER_VALIDATE_BOOL),
                'ownership_percentage' => $party['ownership_percentage'] ?? null,
            ]);
        }
    }

    /** @param list<array<string, mixed>> $signers */
    private function syncSigners(Document $document, DocumentVersion $version, array $signers, int $organizationId, bool $signed, ?int $actorId): void
    {
        $version->signers()->delete();
        foreach ($signers as $signer) {
            $version->signers()->create([
                ...$this->signerSnapshot($signer, $organizationId),
                'document_id' => $document->id,
                'status' => $signed ? DocumentSignerStatus::SIGNED : DocumentSignerStatus::PENDING,
                'signed_at' => $signed ? now() : null,
                'acted_by' => $signed ? $actorId : null,
            ]);
        }
    }

    /** @param array<string, mixed> $signer */
    private function signerSnapshot(array $signer, int $organizationId): array
    {
        $contact = isset($signer['contact_id'])
            ? Contact::withoutGlobalScopes()->where('organization_id', $organizationId)->find($signer['contact_id'])
            : null;
        if (isset($signer['contact_id']) && $contact === null) {
            throw ValidationException::withMessages(['contact_id' => ['The selected signer must belong to the active organization.']]);
        }

        return [
            'contact_id' => $contact?->id,
            'name_snapshot' => $contact?->name ?? $signer['name'],
            'email_snapshot' => $contact?->email ?? ($signer['email'] ?? null),
            'role' => $signer['role'] ?? 'signer',
        ];
    }

    /** @return array<string, mixed>|null */
    private function legacyLeaseAttributes(DocumentDTO $dto, array $attributes, int $organizationId): ?array
    {
        if ($dto->leaseAttributes === null) {
            return null;
        }

        $property = Property::withoutGlobalScopes()->where('organization_id', $organizationId)->find($attributes['property_id'] ?? null);
        $unit = Unit::query()->find($attributes['unit_id'] ?? null);
        $tenant = Contact::withoutGlobalScopes()->where('organization_id', $organizationId)->find($dto->leaseAttributes['tenant_contact_id'] ?? null);

        return [
            ...$dto->leaseAttributes,
            'organization_id' => $organizationId,
            'property_id' => $property?->id,
            'unit_id' => $unit?->id,
            'property_title_snapshot' => $property?->title,
            'unit_name_snapshot' => $unit?->name,
            'tenant_name_snapshot' => $tenant?->name,
            'tenant_email_snapshot' => $tenant?->email,
            'currency' => Organization::query()->whereKey($organizationId)->value('currency') ?? 'BRL',
        ];
    }

    private function ensureCurrentVersion(Document $document, ?int $actorId): DocumentVersion
    {
        $version = $document->versions()->with(['signers', 'signedMedia'])->orderByDesc('version_number')->first();
        if ($version !== null) {
            return $version;
        }

        $version = $document->versions()->create(['version_number' => 1, 'created_by' => $actorId]);
        $legacyPrimary = $document->getFirstMedia(MediaCollection::DOCUMENT_ORIGINAL->value);
        $legacySigned = $document->getFirstMedia(MediaCollection::DOCUMENT_SIGNED->value);
        $version->update([
            'primary_media_id' => $legacyPrimary?->id,
            'signed_media_id' => $legacySigned?->id,
            'finalized_at' => $legacySigned === null ? null : now(),
        ]);

        return $version->refresh()->load(['signers', 'signedMedia']);
    }

    private function recalculateSignatureState(Document $document, DocumentVersion $version, int $actorId): void
    {
        $hasIncompleteSigners = $version->signers()
            ->whereNotIn('status', [DocumentSignerStatus::SIGNED->value, DocumentSignerStatus::WAIVED->value])
            ->exists();
        $complete = $version->signed_media_id !== null && ! $hasIncompleteSigners;
        $document->update([
            'is_signed' => $complete,
            'signed_at' => $complete ? now() : null,
            'signed_by' => $complete ? $actorId : null,
        ]);
    }

    private function lockDocument(Document $document): Document
    {
        return Document::withoutGlobalScopes()
            ->where('organization_id', $this->activeOrganizationId())
            ->whereKey($document->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockMutableDocument(Document $document): Document
    {
        $lockedDocument = $this->lockDocument($document);
        if ($lockedDocument->lifecycle !== DocumentLifecycle::DRAFT || $lockedDocument->is_signed) {
            throw new ConflictHttpException('Create a revision before changing this document.');
        }

        return $lockedDocument;
    }

    private function addPrivateMedia(
        Document $document,
        UploadedFile $file,
        MediaCollection $collection,
        int $actorId,
        DocumentVersion $version,
        ?string $label = null,
        bool $partyVisible = false,
    ): Media {
        $extension = strtolower($file->getClientOriginalExtension());

        return $document->addMedia($file)
            ->usingName($label ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            ->usingFileName(Str::uuid().'.'.$extension)
            ->withCustomProperties([
                'original_name' => $file->getClientOriginalName(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'source' => 'manual',
                'uploaded_by' => $actorId,
                'version_id' => $version->id,
                'version_number' => $version->version_number,
                'label' => $label,
                'party_visible' => $partyVisible,
            ])
            ->addCustomHeaders(['CacheControl' => 'private, no-store'])
            ->toMediaCollection($collection->value);
    }

    /** @param array<int, Media> $mediaItems */
    private function removeRolledBackMedia(array $mediaItems): void
    {
        foreach (array_reverse($mediaItems) as $media) {
            try {
                $media->delete();
            } catch (Throwable) {
                report('A rolled-back document upload could not be removed.');
            }
        }
    }
}
