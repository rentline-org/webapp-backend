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
            ->with(['units.leases', 'media'])
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
        return Property::with(['units.leases', 'organization', 'contactAssignments.contact'])
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
            ->load(['units.leases', 'organization', 'contactAssignments.contact'])
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
        $query = Property::query()->with('organization:id,timezone');

        if (empty($filters['include_archived'])) {
            $query->whereNull('archived_at');
        }

        if (array_key_exists('assigned_property_ids', $filters)) {
            $query->whereIn('properties.id', $filters['assigned_property_ids']);
        }

        if (! empty($filters['with_units'])) {
            $query->with('units');
        }

        if (! empty($filters['property_type'])) {
            $query->where('property_type', $filters['property_type']);
        }

        if (array_key_exists('is_available', $filters) && $filters['is_available'] !== null) {
            $available = filter_var($filters['is_available'], FILTER_VALIDATE_BOOL);
            if ($available) {
                $query->whereHas('units', function (Builder $query): void {
                    $query->whereNull('archived_at')
                        ->where('operational_status', 'active')
                        ->whereDoesntHave('leases', fn (Builder $leaseQuery) => $leaseQuery
                            ->where('workflow_status', 'active')
                            ->whereDate('ends_on', '>=', today()));
                });
            } else {
                $query->whereDoesntHave('units', function (Builder $query): void {
                    $query->whereNull('archived_at')
                        ->where('operational_status', 'active')
                        ->whereDoesntHave('leases', fn (Builder $leaseQuery) => $leaseQuery
                            ->where('workflow_status', 'active')
                            ->whereDate('ends_on', '>=', today()));
                });
            }
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
