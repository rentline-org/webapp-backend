<?php

namespace App\Services\Document;

use App\DTOs\Document\DocumentDTO;
use App\Enums\ContactPersonType;
use App\Enums\DocumentType;
use App\Enums\MediaCollection;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Media;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
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

    public function all(array $filters = []): Collection
    {
        return $this->documentRepository->all(
            $this->activeOrganizationId(),
            $filters
        );
    }

    public function load(Document $document): Document
    {
        $this->ensureDocumentBelongsToOrganization(
            $document,
            $this->activeOrganizationId()
        );

        return $this->documentRepository->load($document);
    }

    public function create(DocumentDTO $dto, int $uploaderId): Document
    {
        if ($dto->originalFile === null) {
            throw ValidationException::withMessages([
                'file' => ['The original document file is required.'],
            ]);
        }

        $createdMedia = [];

        try {
            return DB::transaction(function () use ($dto, $uploaderId, &$createdMedia): Document {
                $organizationId = $this->activeOrganizationId();
                $type = DocumentType::from($dto->attributes['type']);
                $requiresSignature = $type === DocumentType::LEASE
                    ? true
                    : (bool) ($dto->attributes['requires_signature'] ?? false);
                $isSigned = (bool) ($dto->attributes['is_signed'] ?? false);

                $this->ensureSignatureStateIsValid(
                    $requiresSignature,
                    $isSigned,
                    $dto->signedFile !== null,
                );

                $propertyId = $dto->attributes['property_id'] ?? null;
                $unitId = $dto->attributes['unit_id'] ?? null;
                $tenantContactId = $dto->leaseAttributes['tenant_contact_id'] ?? null;
                [$property, $unit, $tenant] = $this->resolveReferences(
                    $organizationId,
                    $type,
                    $propertyId,
                    $unitId,
                    $tenantContactId,
                );
                $leaseAttributes = $type === DocumentType::LEASE
                    ? [
                        ...$this->withLeaseSnapshots(
                        $dto->leaseAttributes ?? [],
                        $property,
                        $unit,
                        $tenant,
                        ),
                        'currency' => Organization::query()
                            ->whereKey($organizationId)
                            ->value('currency') ?? 'BRL',
                    ]
                    : null;

                $document = $this->documentRepository->create([
                    ...$dto->attributes,
                    'organization_id' => $organizationId,
                    'uploaded_by' => $uploaderId,
                    'signed_by' => $isSigned ? $uploaderId : null,
                    'type' => $type->value,
                    'requires_signature' => $requiresSignature,
                    'is_signed' => $isSigned,
                    'signed_at' => $isSigned ? now() : null,
                ], $leaseAttributes);

                $createdMedia[] = $this->addPrivateMedia(
                    $document,
                    $dto->originalFile,
                    MediaCollection::DOCUMENT_ORIGINAL,
                    $uploaderId,
                );

                if ($dto->signedFile !== null) {
                    $createdMedia[] = $this->addPrivateMedia(
                        $document,
                        $dto->signedFile,
                        MediaCollection::DOCUMENT_SIGNED,
                        $uploaderId,
                    );
                }

                return $this->documentRepository->load($document->refresh());
            });
        } catch (Throwable $exception) {
            $this->removeRolledBackMedia($createdMedia);

            throw $exception;
        }
    }

    public function update(Document $document, DocumentDTO $dto): Document
    {
        return DB::transaction(function () use ($document, $dto): Document {
            $organizationId = $this->activeOrganizationId();
            $this->ensureDocumentBelongsToOrganization($document, $organizationId);
            $document->loadMissing('lease');

            if ($dto->originalFile !== null || $dto->signedFile !== null || $dto->hasAttribute('is_signed')) {
                throw ValidationException::withMessages([
                    'file' => ['Document files and signature state use their dedicated endpoints.'],
                ]);
            }

            if (
                $document->is_signed
                && ($dto->hasAttribute('property_id')
                    || $dto->hasAttribute('unit_id')
                    || $dto->leaseAttributes !== null)
            ) {
                throw ValidationException::withMessages([
                    'document' => ['Remove the signed state before changing agreement context or lease terms.'],
                ]);
            }

            $type = $document->type;
            $propertyId = $dto->hasAttribute('property_id')
                ? $dto->attributes['property_id']
                : $document->property_id;
            $unitId = $dto->hasAttribute('unit_id')
                ? $dto->attributes['unit_id']
                : $document->unit_id;
            $tenantContactId = $dto->leaseAttributes['tenant_contact_id']
                ?? $document->lease?->tenant_contact_id;
            $requiresSignature = $type === DocumentType::LEASE
                ? true
                : ($dto->hasAttribute('requires_signature')
                    ? (bool) $dto->attributes['requires_signature']
                    : $document->requires_signature);

            if ($document->is_signed && ! $requiresSignature) {
                throw ValidationException::withMessages([
                    'requires_signature' => ['Remove the signed state before disabling signature requirements.'],
                ]);
            }

            [$property, $unit, $tenant] = $this->resolveReferences(
                $organizationId,
                $type,
                $propertyId,
                $unitId,
                $tenantContactId,
            );
            $leaseAttributes = $dto->leaseAttributes;

            if ($type === DocumentType::LEASE && $leaseAttributes !== null) {
                $leaseAttributes = $this->withLeaseSnapshots(
                    $leaseAttributes,
                    $property,
                    $unit,
                    $tenant,
                );
                $leaseAttributes['currency'] = $document->lease?->currency
                    ?? Organization::query()->whereKey($organizationId)->value('currency')
                    ?? 'BRL';
            }

            $attributes = [
                ...$dto->attributes,
                'requires_signature' => $requiresSignature,
            ];
            unset($attributes['type'], $attributes['is_signed']);

            return $this->documentRepository->update(
                $document,
                $attributes,
                $leaseAttributes,
            );
        });
    }

    public function markSigned(Document $document, UploadedFile $signedFile, int $signedBy): Document
    {
        $createdMedia = [];

        try {
            return DB::transaction(function () use ($document, $signedFile, $signedBy, &$createdMedia): Document {
                $organizationId = $this->activeOrganizationId();
                $lockedDocument = Document::withoutGlobalScopes()
                    ->where('organization_id', $organizationId)
                    ->whereKey($document->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $lockedDocument->requires_signature) {
                    throw ValidationException::withMessages([
                        'signed_file' => ['This document does not require a signature.'],
                    ]);
                }

                if ($lockedDocument->hasMedia(MediaCollection::DOCUMENT_SIGNED->value)) {
                    throw new ConflictHttpException(
                        'Remove the current signed copy before uploading another one.'
                    );
                }

                $createdMedia[] = $this->addPrivateMedia(
                    $lockedDocument,
                    $signedFile,
                    MediaCollection::DOCUMENT_SIGNED,
                    $signedBy,
                );
                $lockedDocument->update([
                    'is_signed' => true,
                    'signed_at' => now(),
                    'signed_by' => $signedBy,
                ]);

                return $this->documentRepository->load($lockedDocument->refresh());
            });
        } catch (Throwable $exception) {
            $this->removeRolledBackMedia($createdMedia);

            throw $exception;
        }
    }

    public function markUnsigned(Document $document): Document
    {
        return DB::transaction(function () use ($document): Document {
            $organizationId = $this->activeOrganizationId();
            $lockedDocument = Document::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedDocument->clearMediaCollection(MediaCollection::DOCUMENT_SIGNED->value);
            $lockedDocument->update([
                'is_signed' => false,
                'signed_at' => null,
                'signed_by' => null,
            ]);

            return $this->documentRepository->load($lockedDocument->refresh());
        });
    }

    public function delete(Document $document): bool
    {
        $this->ensureDocumentBelongsToOrganization(
            $document,
            $this->activeOrganizationId()
        );

        return $this->documentRepository->delete($document);
    }

    private function activeOrganizationId(): int
    {
        $organizationId = $this->activeOrganizationContext->id();

        if ($organizationId === null) {
            throw new AuthorizationException('An active organization is required.');
        }

        return $organizationId;
    }

    private function ensureDocumentBelongsToOrganization(Document $document, int $organizationId): void
    {
        if ($document->organization_id !== $organizationId) {
            throw new AuthorizationException;
        }
    }

    private function ensureSignatureStateIsValid(
        bool $requiresSignature,
        bool $isSigned,
        bool $hasSignedFile,
    ): void {
        if ($isSigned && ! $requiresSignature) {
            throw ValidationException::withMessages([
                'is_signed' => ['A document can only be marked signed when a signature is required.'],
            ]);
        }

        if ($isSigned && ! $hasSignedFile) {
            throw ValidationException::withMessages([
                'signed_file' => ['Upload the signed document before marking it as signed.'],
            ]);
        }

        if ($hasSignedFile && ! $isSigned) {
            throw ValidationException::withMessages([
                'is_signed' => ['Mark the document as signed when uploading a signed copy.'],
            ]);
        }
    }

    /**
     * @return array{0: Property|null, 1: Unit|null, 2: Contact|null}
     */
    private function resolveReferences(
        int $organizationId,
        DocumentType $type,
        ?int $propertyId,
        ?int $unitId,
        ?int $tenantContactId,
    ): array {
        if ($type === DocumentType::LEASE && ($propertyId === null || $unitId === null || $tenantContactId === null)) {
            throw ValidationException::withMessages([
                'lease' => ['A lease requires a property, unit, and tenant contact.'],
            ]);
        }

        if ($unitId !== null && $propertyId === null) {
            throw ValidationException::withMessages([
                'unit_id' => ['Select a property before selecting a unit.'],
            ]);
        }

        $property = $propertyId === null
            ? null
            : Property::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->whereKey($propertyId)
                ->first();

        if ($propertyId !== null && $property === null) {
            throw ValidationException::withMessages([
                'property_id' => ['The selected property must belong to the active organization.'],
            ]);
        }

        $unit = $unitId === null
            ? null
            : Unit::query()
                ->whereKey($unitId)
                ->where('property_id', $propertyId)
                ->whereHas('property', fn ($query) => $query
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organizationId))
                ->first();

        if ($unitId !== null && $unit === null) {
            throw ValidationException::withMessages([
                'unit_id' => ['The selected unit must belong to the selected property.'],
            ]);
        }

        $tenant = $tenantContactId === null
            ? null
            : Contact::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('type', ContactPersonType::TENANT->value)
                ->whereKey($tenantContactId)
                ->first();

        if ($tenantContactId !== null && $tenant === null) {
            throw ValidationException::withMessages([
                'lease.tenant_contact_id' => ['The selected tenant must belong to the active organization.'],
            ]);
        }

        return [$property, $unit, $tenant];
    }

    /** @param array<string, mixed> $leaseAttributes */
    private function withLeaseSnapshots(
        array $leaseAttributes,
        ?Property $property,
        ?Unit $unit,
        ?Contact $tenant,
    ): array {
        return [
            ...$leaseAttributes,
            'property_title_snapshot' => $property?->title,
            'unit_name_snapshot' => $unit?->name,
            'tenant_name_snapshot' => $tenant?->name,
            'tenant_email_snapshot' => $tenant?->email,
        ];
    }

    private function addPrivateMedia(
        Document $document,
        UploadedFile $file,
        MediaCollection $collection,
        int $actorId,
    ): Media {
        $extension = strtolower($file->getClientOriginalExtension());

        return $document->addMedia($file)
            ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            ->usingFileName(Str::uuid().'.'.$extension)
            ->withCustomProperties([
                'original_name' => $file->getClientOriginalName(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'source' => 'manual',
                'uploaded_by' => $actorId,
            ])
            ->addCustomHeaders([
                'CacheControl' => 'private, no-store',
            ])
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
