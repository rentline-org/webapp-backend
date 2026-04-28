<?php

namespace App\Services\Property;

use App\DTOs\Property\PropertyDTO;
use App\Helpers\OrganizationHelper;
use App\Models\Property;
use App\Repositories\Contracts\PropertyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;

class PropertyService
{
    public function __construct(
        protected PropertyRepositoryInterface $propertyRepository
    ) {
        //
    }

    /**
     * Return a paginated list of properties.
     *
     * Filtering is handled by the repository.
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->propertyRepository->paginate($filters, $perPage);
    }

    /**
     * Return all properties matching the given filters.
     *
     * Useful for exports, dropdowns, or smaller result sets.
     */
    public function all(array $filters = []): Collection
    {
        return $this->propertyRepository->all($filters);
    }

    /** Fetch a property by its ID. */
    public function findById(int $id): ?Property
    {
        return $this->propertyRepository->findById($id);
    }

    /** Fetch a property by its slug. */
    public function findBySlug(string $slug): ?Property
    {
        return $this->propertyRepository->findBySlug($slug);
    }

    /**
     * Create a property for the active organization.
     *
     * The active organization is resolved from the request context
     * that your middleware sets.
     *
     * Landlord authorization should be enforced by policy.
     */
    public function create(PropertyDTO $dto): Property
    {
        $organizationId = app(OrganizationHelper::class)->get();

        if (! $organizationId) {
            throw new RuntimeException('No active organization context found.');
        }

        $data = $dto->toArray();
        // $data['organization_id'] = $organizationId;

        // Never trust organization_id from the request payload.
        unset($data['organization_id']);

        $data['organization_id'] = $organizationId;

        return $this->propertyRepository->create($data);
    }

    /**
     * Update an existing property.
     *
     * Organization ownership is never changed here.
     */
    public function update(Property $property, PropertyDTO $dto): Property
    {
        $data = $dto->toArray();

        // Prevent cross-organization tampering.
        unset($data['organization_id']);

        return $this->propertyRepository->update($property, $data);
    }

    /**
     * Delete a property.
     *
     * Units should cascade through the database relationship.
     */
    public function delete(Property $property): bool
    {
        return $this->propertyRepository->delete($property);
    }
}
