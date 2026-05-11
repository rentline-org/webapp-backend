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
    public function all(array $filters = []): Collection
    {
        return $this->query($filters)
            ->with(['units'])
            ->withCount('units')
            ->get();
    }

    public function create(array $data): Property
    {
        return DB::transaction(function () use ($data) {
            if (empty($data['slug']) && ! empty($data['title'])) {
                $data['slug'] = Str::slug($data['title']);
            }

            return Property::create($data)->loadCount('units');
        });
    }

    public function delete(Property $property): bool
    {
        /** @var Property $property */
        return DB::transaction($property->delete(...));
    }

    public function findById(int $id): ?Property
    {
        return Property::with(['units', 'organization'])
            ->withCount('units')
            ->find($id);
    }

    public function findBySlug(string $slug): ?Property
    {
        $property = Property::query()->where('slug', $slug)->first();

        if (! $property) {
            throw new ModelNotFoundException("Property with slug '{$slug}' not found.");
        }

        return $property
            ->load(['units', 'organization'])
            ->loadCount('units');
    }

    public function paginate(
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->query($filters)
            ->withCount('units')
            ->paginate($perPage);
    }

    public function update(Property $property, array $data): Property
    {
        return DB::transaction(function () use ($property, $data) {
            $property->update($data);

            return $property->refresh()->loadCount('units');
        });
    }

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

        // 🔥 fully unit-driven pricing
        if (isset($filters['min_rent_price'])) {
            $query->whereHas('units', fn (Builder $q) => $q->where('rent_price', '>=', $filters['min_rent_price'])
            );
        }

        if (isset($filters['max_rent_price'])) {
            $query->whereHas('units', fn (Builder $q) => $q->where('rent_price', '<=', $filters['max_rent_price'])
            );
        }

        return $query->latest();
    }
}
