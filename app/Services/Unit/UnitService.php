<?php

namespace App\Services\Unit;

use App\DTOs\Unit\UnitDTO;
use App\Enums\PropertyType;
use App\Enums\UnitType;
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
    ) {}

    public function paginate(
        Property $property,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->unitRepository->paginateByProperty($property, $filters, $perPage);
    }

    public function all(Property $property, array $filters = []): Collection
    {
        return $this->unitRepository->allByProperty($property, $filters);
    }

    public function findById(Property $property, int $unitId): ?Unit
    {
        return $this->unitRepository->findById($property, $unitId);
    }

    /** Create a new unit under a property. */
    public function create(Property $property, UnitDTO $dto): Unit
    {
        $this->ensureValidUnitType($property, $dto->unit_type);

        return $this->unitRepository->create(
            $property,
            $dto->toArray()
        );
    }

    /** Update a unit. */
    public function update(Unit $unit, UnitDTO $dto): Unit
    {
        $this->ensureValidUnitType($unit->property, $dto->unit_type);

        return $this->unitRepository->update(
            $unit,
            $dto->toArray()
        );
    }

    public function delete(Unit $unit): bool
    {
        return $this->unitRepository->delete($unit);
    }

    /** Validate that a unit type is allowed for the given property type. */
    protected function ensureValidUnitType(Property $property, UnitType $unitType): void
    {
        $allowed = $this->allowedUnitTypes($property->property_type);

        if (! in_array($unitType, $allowed, true)) {
            throw new InvalidArgumentException(
                "Unit type '{$unitType->value}' is not allowed for property type '{$property->property_type->value}'."
            );
        }
    }

    /** Define allowed unit types per property type. */
    protected function allowedUnitTypes(PropertyType $propertyType): array
    {
        return match ($propertyType) {
            PropertyType::SINGLE_UNIT => [
                UnitType::HOUSE,
                UnitType::ROOM,
                UnitType::OFFICE,
                UnitType::STUDIO,
                UnitType::WAREHOUSE,
            ],

            PropertyType::MULTI_UNIT => [
                UnitType::APARTMENT,
                UnitType::STUDIO,
                UnitType::ROOM,
                UnitType::OFFICE,
            ],

            PropertyType::LAND => [
                UnitType::LAND,
            ],
        };
    }
}
