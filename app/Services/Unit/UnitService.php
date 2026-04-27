<?php

namespace App\Services\Unit;

use App\DTOs\Unit\UnitDTO;
use App\Enums\PropertyType;
use App\Models\Property;
use App\Models\Unit;
use App\Repositories\Contracts\UnitRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class UnitService
{
    public function __construct(
        protected UnitRepositoryInterface $unitRepository
    ) {
        //
    }

    /** Get paginated units for a property. */
    public function paginate(
        Property $property,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->unitRepository->paginateByProperty($property, $filters, $perPage);
    }

    /** Get all units for a property. */
    public function all(Property $property, array $filters = []): Collection
    {
        return $this->unitRepository->allByProperty($property, $filters);
    }

    /** Find a unit by ID within a property. */
    public function findById(Property $property, int $unitId): ?Unit
    {
        return $this->unitRepository->findById($property, $unitId);
    }

    /**
     * Create a new unit under a property.
     *
     * Only allowed for apartment-type properties.
     */
    public function create(Property $property, UnitDTO $dto): Unit
    {
        $this->ensureSupportsUnits($property);

        return $this->unitRepository->create(
            $property,
            $dto->toArray()
        );
    }

    /**
     * Update a unit.
     *
     * Assumes the unit already belongs to the property via route binding or lookup.
     */
    public function update(Unit $unit, UnitDTO $dto): Unit
    {
        $this->ensureSupportsUnits($unit->property);

        return $this->unitRepository->update(
            $unit,
            $dto->toArray()
        );
    }

    /** Delete a unit. */
    public function delete(Unit $unit): bool
    {
        return $this->unitRepository->delete($unit);
    }

    /**
     * Ensure the property type supports units.
     *
     * Only apartments can have units.
     */
    protected function ensureSupportsUnits(Property $property): void
    {

        if ($property->property_type !== PropertyType::APARTMENT) {
            throw new InvalidArgumentException('This property type cannot have units.');
        }
    }
}
