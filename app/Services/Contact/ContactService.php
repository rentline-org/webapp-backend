<?php

namespace App\Services\Contact;

use App\DTOs\Contact\ContactDTO;
use App\Models\Contact;
use App\Models\Property;
use App\Repositories\Contracts\ContactRepositoryInterface;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContactService
{
    public function __construct(
        protected ContactRepositoryInterface $contactRepository,
        protected ActiveOrganizationContext $activeOrganizationContext,
    ) {}

    public function all(array $filters = []): Collection
    {
        return $this->contactRepository->all(
            $this->activeOrganizationId(),
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

            return $this->contactRepository->create(
                [
                    ...$dto->toArray(),
                    'organization_id' => $organizationId,
                ],
                $propertyIds
            );
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

            return $this->contactRepository->update(
                $contact,
                $dto->toArray(),
                $propertyIds
            );
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
}
