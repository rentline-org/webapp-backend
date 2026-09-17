<?php

namespace App\Services\Contact;

use App\DTOs\Contact\ContactDTO;
use App\Enums\ContactAssignmentRole;
use App\Enums\ContactAssignmentSource;
use App\Enums\ContactPersonType;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Property;
use App\Models\User;
use App\Repositories\Contracts\ContactRepositoryInterface;
use App\Services\Organization\ActiveOrganizationContext;
use App\Services\Organization\AssignedPropertyAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ContactService
{
    public function __construct(
        protected ContactRepositoryInterface $contactRepository,
        protected ActiveOrganizationContext $activeOrganizationContext,
        protected AssignedPropertyAccess $assignedPropertyAccess,
    ) {}

    public function all(array $filters = [], ?User $viewer = null): Collection
    {
        $organizationId = $this->activeOrganizationId();
        if ($viewer !== null && $this->assignedPropertyAccess->isRestrictedAgent($viewer, $organizationId)) {
            $filters['assigned_scope'] = $this->assignedPropertyAccess->scope($viewer, $organizationId);
        }

        return $this->contactRepository->all(
            $organizationId,
            $filters
        );
    }

    public function create(ContactDTO $dto): Contact
    {
        return DB::transaction(function () use ($dto): Contact {
            $organizationId = $this->activeOrganizationId();
            $propertyIds = $this->validatedPropertyIds(
                $dto->propertyIds ?? [],
                $organizationId
            );
            $this->ensureAgentCanUseProperties($propertyIds, $organizationId);

            $contact = $this->contactRepository->create(
                [
                    ...$this->contactAttributes($dto),
                    'organization_id' => $organizationId,
                ],
                $propertyIds
            );
            $this->syncManualPropertyAssignments($contact, $propertyIds, $dto->type);

            return $contact->load(['properties', 'assignments.property', 'assignments.unit']);
        });
    }

    public function update(Contact $contact, ContactDTO $dto): Contact
    {
        return DB::transaction(function () use ($contact, $dto): Contact {
            $organizationId = $this->activeOrganizationId();
            $this->ensureContactBelongsToOrganization($contact, $organizationId);

            $propertyIds = $dto->propertyIds === null
                ? null
                : $this->validatedPropertyIds($dto->propertyIds, $organizationId);
            if ($propertyIds !== null) {
                $this->ensureAgentCanUseProperties($propertyIds, $organizationId);
            }

            $updated = $this->contactRepository->update(
                $contact,
                $this->contactAttributes($dto),
                $propertyIds
            );
            $selectedPropertyIds = $propertyIds
                ?? $updated->properties()->pluck('properties.id')->map(fn ($id): int => (int) $id)->all();
            $this->syncManualPropertyAssignments($updated, $selectedPropertyIds, $dto->type);

            return $updated->load(['properties', 'assignments.property', 'assignments.unit']);
        });
    }

    public function delete(Contact $contact): bool
    {
        $this->ensureContactBelongsToOrganization(
            $contact,
            $this->activeOrganizationId()
        );

        return $this->contactRepository->delete($contact);
    }

    private function activeOrganizationId(): int
    {
        $organizationId = $this->activeOrganizationContext->id();

        if ($organizationId === null) {
            throw new AuthorizationException('An active organization is required.');
        }

        return $organizationId;
    }

    private function ensureContactBelongsToOrganization(Contact $contact, int $organizationId): void
    {
        if ($contact->organization_id !== $organizationId) {
            throw new AuthorizationException;
        }
    }

    /** @return array<string, mixed> */
    private function contactAttributes(ContactDTO $dto): array
    {
        $attributes = $dto->toArray();

        if (! $dto->taxIdProvided) {
            return $attributes;
        }

        if ($dto->taxId === null || trim($dto->taxId) === '') {
            return [
                ...$attributes,
                'tax_id_type' => null,
                'tax_id_encrypted' => null,
                'tax_id_hash' => null,
                'tax_id_last4' => null,
            ];
        }

        $normalized = preg_replace('/\D/', '', $dto->taxId) ?? '';

        return [
            ...$attributes,
            'tax_id_encrypted' => Crypt::encryptString($normalized),
            'tax_id_hash' => hash_hmac('sha256', $normalized, (string) config('app.key')),
            'tax_id_last4' => substr($normalized, -4),
        ];
    }

    /**
     * @param  array<int, int> $propertyIds
     * @return array<int, int>
     */
    private function validatedPropertyIds(array $propertyIds, int $organizationId): array
    {
        $propertyIds = array_values(array_unique($propertyIds));

        if ($propertyIds === []) {
            return [];
        }

        $validPropertyIds = Property::query()
            ->where('organization_id', $organizationId)
            ->whereKey($propertyIds)
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        if (count($validPropertyIds) !== count($propertyIds)) {
            throw ValidationException::withMessages([
                'property_ids' => ['Every selected property must belong to the active organization.'],
            ]);
        }

        return $propertyIds;
    }

    /** @param list<int> $propertyIds */
    private function ensureAgentCanUseProperties(array $propertyIds, int $organizationId): void
    {
        $user = auth()->user();
        if ($user === null || ! $this->assignedPropertyAccess->isRestrictedAgent($user, $organizationId)) {
            return;
        }

        if ($propertyIds === []) {
            throw ValidationException::withMessages([
                'property_ids' => ['Agents must connect a new contact to an assigned property.'],
            ]);
        }

        foreach ($propertyIds as $propertyId) {
            if (! $this->assignedPropertyAccess->canManageProperty($user, $organizationId, $propertyId)) {
                throw new AuthorizationException('The selected property is not assigned to this agent.');
            }
        }
    }

    /** @param list<int> $propertyIds */
    private function syncManualPropertyAssignments(
        Contact $contact,
        array $propertyIds,
        ContactPersonType $contactType,
    ): void {
        $role = match ($contactType) {
            ContactPersonType::OWNER => ContactAssignmentRole::OWNER,
            ContactPersonType::AGENT => ContactAssignmentRole::AGENT,
            ContactPersonType::TENANT => ContactAssignmentRole::TENANT,
        };
        $timezone = Organization::query()->whereKey($contact->organization_id)->value('timezone')
            ?? config('app.timezone');
        $today = Carbon::now($timezone)->startOfDay();
        $activeAssignments = $contact->assignments()
            ->where('source', ContactAssignmentSource::MANUAL->value)
            ->whereNull('unit_id')
            ->whereNull('lease_id')
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))
            ->get();

        foreach ($activeAssignments as $assignment) {
            if (! in_array($assignment->property_id, $propertyIds, true) || $assignment->role !== $role) {
                $assignment->update(['ends_on' => $today->copy()->subDay()]);
            }
        }

        foreach ($propertyIds as $propertyId) {
            $existing = $activeAssignments->first(
                fn ($assignment): bool => $assignment->property_id === $propertyId && $assignment->role === $role
            );
            if ($existing !== null) {
                $existing->update(['ends_on' => null, 'is_primary' => true]);

                continue;
            }

            $contact->assignments()->create([
                'organization_id' => $contact->organization_id,
                'property_id' => $propertyId,
                'role' => $role,
                'source' => ContactAssignmentSource::MANUAL,
                'is_primary' => true,
                'starts_on' => $today,
            ]);
        }
    }
}
