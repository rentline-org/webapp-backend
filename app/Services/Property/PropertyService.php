<?php

namespace App\Services\Property;

use App\DTOs\Property\PropertyDTO;
use App\DTOs\Unit\UnitDTO;
use App\Events\PropertyCreated;
use App\Models\Property;
use App\Repositories\Contracts\PropertyRepositoryInterface;
use App\Repositories\Contracts\UnitRepositoryInterface;
use App\Services\Organization\ActiveOrganizationContext;
use App\Services\Organization\AssignedPropertyAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PropertyService
{
    public function __construct(
        protected PropertyRepositoryInterface $propertyRepository,
        protected UnitRepositoryInterface $unitRepository,
        protected AssignedPropertyAccess $assignedPropertyAccess,
    ) {
        //
    }

    /**
     * Return a paginated list of properties.
     *
     * Filtering is handled by the repository.
     */
    public function paginate(array $filters = [], int $perPage = 15, ?User $viewer = null): LengthAwarePaginator
    {
        $filters = $this->withAssignedPropertyScope($filters, $viewer);

        return $this->propertyRepository->paginate($filters, $perPage);
    }

    /**
     * Return all properties matching the given filters.
     *
     * Useful for exports, dropdowns, or smaller result sets.
     */
    public function all(array $filters = [], ?User $viewer = null): Collection
    {
        $filters = $this->withAssignedPropertyScope($filters, $viewer);

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
    public function create(PropertyDTO $dto, array $units): Property
    {
        $organizationId = app(ActiveOrganizationContext::class)->id();
        $user = auth()->user();

        if (! $organizationId) {
            throw new RuntimeException('No active organization context found.');
        }

        $data = $dto->toArray();
        unset($data['organization_id']);

        $data['organization_id'] = $organizationId;

        $createdProperty = DB::transaction(function () use ($data, $dto, $units): Property {
            $createdProperty = $this->propertyRepository->create($data);

            /** @var UnitDTO $unitDTO */
            foreach ($units as $unitDTO) {
                if (! in_array($unitDTO->unit_type, $dto->property_type->allowedUnitTypes(), true)) {
                    throw ValidationException::withMessages([
                        'units' => ['Every unit type must be compatible with the property type.'],
                    ]);
                }

                $this->unitRepository->create($createdProperty, $unitDTO->toArray());
            }

            return $createdProperty->load(['units']);
        });

        event(new PropertyCreated($user, $createdProperty));

        return $createdProperty;
    }

    /**
     * Update an existing property.
     *
     * Organization ownership is never changed here.
     */
    public function update(Property $property, PropertyDTO $dto): Property
    {
        $hasIncompatibleUnit = $property->units()
            ->whereNull('archived_at')
            ->whereNotIn('unit_type', $dto->property_type->allowedUnitTypeValues())
            ->exists();

        if ($hasIncompatibleUnit) {
            throw ValidationException::withMessages([
                'property_type' => ['The property type is incompatible with one or more existing units.'],
            ]);
        }

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
        return DB::transaction(function () use ($property): bool {
            $property->units()->update([
                'operational_status' => 'off_market',
                'archived_at' => now(),
            ]);

            return $property->update([
                'operational_status' => 'off_market',
                'archived_at' => now(),
            ]);
        });
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function withAssignedPropertyScope(array $filters, ?User $viewer): array
    {
        $organizationId = app(ActiveOrganizationContext::class)->id();
        if ($viewer !== null && $organizationId !== null && $this->assignedPropertyAccess->isRestrictedAgent($viewer, $organizationId)) {
            $filters['assigned_property_ids'] = $this->assignedPropertyAccess->scope($viewer, $organizationId)['property_ids'];
        }

        return $filters;
    }
}
