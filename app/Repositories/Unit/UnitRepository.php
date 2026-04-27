<?php

namespace App\Repositories\Unit;

use App\Models\Property;
use App\Models\Unit;
use App\Repositories\Contracts\UnitRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UnitRepository implements UnitRepositoryInterface
{
    /**
     * Get all units for a given property.
     *
     * This is useful for small lists or internal usage where pagination is not required.
     */
    public function allByProperty(Property $property, array $filters = []): Collection
    {
        return $this->query($property, $filters)->get();
    }

    /**
     * Create a new unit under a property.
     *
     * The relationship ensures the correct property_id is set.
     */
    public function create(Property $property, array $data): Unit
    {
        return DB::transaction(function () use ($property, $data) {
            return $property->units()->create($data);
        });
    }

    /** Delete a unit. */
    public function delete(Unit $unit): bool
    {
        return DB::transaction(function () use ($unit) {
            return (bool) $unit->delete();
        });
    }

    /**
     * Find a unit by ID scoped to a property.
     *
     * Prevents accessing units that do not belong to the given property.
     */
    public function findById(Property $property, int $id): ?Unit
    {
        return $property->units()->where('id', $id)->first();
    }

    /**
     * Paginate units for a property.
     *
     * Uses the same filters as `allByProperty`.
     */
    public function paginateByProperty(
        Property $property,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->query($property, $filters)->paginate($perPage);
    }

    /** Update a unit. */
    public function update(Unit $unit, array $data): Unit
    {
        return DB::transaction(function () use ($unit, $data) {
            $unit->update($data);

            return $unit->refresh();
        });
    }

    /**
     * Build the base query for a property's units.
     *
     * All unit queries MUST go through the property relationship
     * to ensure proper multi-tenant isolation.
     */
    protected function query(Property $property, array $filters = []): Builder
    {
        $query = $property->units()->getQuery();

        if (array_key_exists('is_available', $filters) && $filters['is_available'] !== null) {
            $query->where('is_available', (bool) $filters['is_available']);
        }

        if (! empty($filters['min_rent_price'])) {
            $query->where('rent_price', '>=', $filters['min_rent_price']);
        }

        if (! empty($filters['max_rent_price'])) {
            $query->where('rent_price', '<=', $filters['max_rent_price']);
        }

        if (! empty($filters['bedrooms'])) {
            $query->where('bedrooms', $filters['bedrooms']);
        }

        if (! empty($filters['bathrooms'])) {
            $query->where('bathrooms', $filters['bathrooms']);
        }

        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';

            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('description', 'like', $search);
            });
        }

        return $query->latest();
    }
}
