<?php

namespace App\Repositories\Property;

use App\Models\Property;
use App\Repositories\Contracts\PropertyRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertyRepository implements PropertyRepositoryInterface
{
    /**
     * Return all properties matching the given filters.
     *
     * This is useful for exports, dropdowns, and admin screens where
     * pagination is not required.
     */
    public function all(array $filters = []): Collection
    {
        return $this->query($filters)
            ->withCount('units')
            ->get();
    }

    /**
     * Create a new property record.
     *
     * If the slug is not provided, it is generated from the title.
     * The record is created inside a transaction for safety.
     */
    public function create(array $data): Property
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['slug']) && ! empty($data['title'])) {
                $data['slug'] = Str::slug($data['title']);
            }

            return Property::create($data);
        });
    }

    /**
     * Delete a property record.
     *
     * Related units should cascade through the foreign key constraint.
     */
    public function delete(Property $property): bool
    {
        return DB::transaction(function () use ($property) {
            return (bool) $property->delete();
        });
    }

    /**
     * Find a property by its primary key.
     *
     * Returns null if no property is found.
     */
    public function findById(int $id): ?Property
    {
        $property = Property::find($id);

        if ($property) {
            $property->load(['units', 'organization']);
        }

        return $property;
    }

    /**
     * Find a property by its slug.
     *
     * This is useful for routes that use human-readable URLs.
     */
    public function findBySlug(string $slug): ?Property
    {
        $property = Property::where('slug', $slug)->first();

        if (! $property) {
            throw new ModelNotFoundException("Property with slug '{$slug}' not found.");
        }

        // if ($property) {
        $property->load(['units', 'organization']);
        // }

        return $property;
    }

    /**
     * Return a paginated list of properties matching the given filters.
     *
     * The filters behave the same way as in `all()`.
     */
    public function paginate(
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->query($filters)->paginate($perPage);
    }

    /**
     * Update an existing property record.
     *
     * If the slug is missing and the property does not already have one,
     * a slug is generated from the title.
     */
    public function update(Property $property, array $data): Property
    {
        return DB::transaction(function () use ($property, $data) {
            if (empty($data['slug']) && ! empty($data['title']) && empty($property->slug)) {
                $data['slug'] = Str::slug($data['title']);
            }

            $property->update($data);

            return $property->refresh();
        });
    }

    /**
     * Build the base query used by read operations.
     *
     * Keeping this in one place makes `all()` and `paginate()` behave the same
     * and keeps filtering logic easy to maintain.
     */
    protected function query(array $filters = []): Builder
    {
        $query = Property::query();

        if (! empty($filters['with_units'])) {
            $query->with('units');
        }

        if (! empty($filters['property_type'])) {
            $query->where('property_type', $filters['property_type']);
        }

        if (array_key_exists('is_available', $filters) && $filters['is_available'] !== null) {
            $query->where('is_available', (bool) $filters['is_available']);
        }

        if (! empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        if (! empty($filters['state'])) {
            $query->where('state', $filters['state']);
        }

        if (! empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        if (! empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';

            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('address', 'like', $search)
                    ->orWhere('city', 'like', $search)
                    ->orWhere('state', 'like', $search)
                    ->orWhere('postal_code', 'like', $search);
            });
        }

        if (isset($filters['min_rent_price'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('rent_price', '>=', $filters['min_rent_price'])
                    ->orWhereHas('units', function (Builder $unitQuery) use ($filters) {
                        $unitQuery->where('rent_price', '>=', $filters['min_rent_price']);
                    });
            });
        }

        if (isset($filters['max_rent_price'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('rent_price', '<=', $filters['max_rent_price'])
                    ->orWhereHas('units', function (Builder $unitQuery) use ($filters) {
                        $unitQuery->where('rent_price', '<=', $filters['max_rent_price']);
                    });
            });
        }

        return $query->latest();
    }
}
