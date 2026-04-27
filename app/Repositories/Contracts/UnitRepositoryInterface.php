<?php

namespace App\Repositories\Contracts;

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UnitRepositoryInterface
{
    public function paginateByProperty(Property $property, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function allByProperty(Property $property, array $filters = []): Collection;

    public function findById(Property $property, int $id): ?Unit;

    public function create(Property $property, array $data): Unit;

    public function update(Unit $unit, array $data): Unit;

    public function delete(Unit $unit): bool;
}
