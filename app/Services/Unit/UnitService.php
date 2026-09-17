<?php

namespace App\Services\Unit;

use App\DTOs\Unit\UnitDTO;
use App\Enums\PropertyType;
use App\Enums\UnitType;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Repositories\Contracts\UnitRepositoryInterface;
use App\Services\Organization\AssignedPropertyAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UnitService
{
    public function __construct(
        protected UnitRepositoryInterface $unitRepository,
        protected AssignedPropertyAccess $assignedPropertyAccess,
    ) {}

    public function paginate(
        Property $property,
        array $filters = [],
        int $perPage = 15,
        ?User $viewer = null,
    ): LengthAwarePaginator {
        $filters = $this->withAssignedUnitScope($property, $filters, $viewer);

        return $this->unitRepository->paginateByProperty($property, $filters, $perPage);
    }

    public function all(Property $property, array $filters = [], ?User $viewer = null): Collection
    {
        $filters = $this->withAssignedUnitScope($property, $filters, $viewer);

        return $this->unitRepository->allByProperty($property, $filters);
    }

    public function findById(Property $property, int $unitId): ?Unit
    {
        return $this->unitRepository->findById($property, $unitId);
    }

    /** Create a new unit under a property. */
    public function create(Property $property, UnitDTO $dto): Unit
    {
        if ($property->archived_at !== null) {
            throw new ConflictHttpException('Units cannot be added to an archived property.');
        }

        $this->ensureValidUnitType($property, $dto->unit_type);

        return $this->unitRepository->create(
            $property,
            $dto->toArray()
        );
    }

    /** Update a unit. */
    public function update(Unit $unit, UnitDTO $dto): Unit
    {
        if ($unit->archived_at !== null || $unit->property?->archived_at !== null) {
            throw new ConflictHttpException('Archived units cannot be edited.');
        }

        $this->ensureValidUnitType($unit->property, $dto->unit_type);

        return $this->unitRepository->update(
            $unit,
            $dto->toArray()
        );
    }

    public function delete(Unit $unit): bool
    {
        $unit->loadMissing('property');

        if ($unit->archived_at !== null) {
            return true;
        }

        if ($unit->property?->archived_at === null
            && $unit->property?->units()->whereNull('archived_at')->count() <= 1) {
            throw new ConflictHttpException('Archive the property instead of its last rentable unit.');
        }

        return $unit->update([
            'operational_status' => 'off_market',
            'archived_at' => now(),
        ]);
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
        return $propertyType->allowedUnitTypes();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function withAssignedUnitScope(Property $property, array $filters, ?User $viewer): array
    {
        if ($viewer === null || ! $this->assignedPropertyAccess->isRestrictedAgent($viewer, $property->organization_id)) {
            return $filters;
        }

        $scope = $this->assignedPropertyAccess->scope($viewer, $property->organization_id);
        if (! in_array($property->id, $scope['broad_property_ids'], true)) {
            $filters['assigned_unit_ids'] = $scope['unit_ids'];
        }

        return $filters;
    }
}
