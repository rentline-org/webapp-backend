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
    public function allByProperty(Property $property, array $filters = []): Collection
    {
        return $this->query($property, $filters)->get();
    }

    public function create(Property $property, array $data): Unit
    {
        return DB::transaction(fn () => $property->units()->create($data)
        );
    }

    public function delete(Unit $unit): bool
    {
        /** @var Unit $unit */
        return DB::transaction($unit->delete(...));
    }

    public function findById(Property $property, int $id): ?Unit
    {
        return $property->units()->whereKey($id)->first();
    }

    public function paginateByProperty(
        Property $property,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        return $this->query($property, $filters)->paginate($perPage);
    }

    public function update(Unit $unit, array $data): Unit
    {
        return DB::transaction(function () use ($unit, $data) {
            $unit->update($data);

            return $unit->refresh();
        });
    }

    protected function query(Property $property, array $filters = [])
    {
        $query = $property->units()->with(['leases', 'contactAssignments.contact']);

        if (empty($filters['include_archived'])) {
            $query->whereNull('archived_at');
        }

        if (array_key_exists('is_available', $filters) && $filters['is_available'] !== null) {
            $available = filter_var($filters['is_available'], FILTER_VALIDATE_BOOL);
            $query->where('operational_status', 'active');
            $method = $available ? 'whereDoesntHave' : 'whereHas';
            $query->{$method}('leases', fn (Builder $leaseQuery) => $leaseQuery
                ->where('workflow_status', 'active')
                ->whereDate('ends_on', '>=', today()));
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
