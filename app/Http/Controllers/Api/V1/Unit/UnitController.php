<?php

namespace App\Http\Controllers\Api\V1\Unit;

use App\DTOs\Unit\UnitDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\UnitInsertRequest;
use App\Http\Requests\Unit\UnitUpdateRequest;
use App\Http\Resources\Unit\UnitResource;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Unit\UnitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UnitController extends Controller
{
    public function __construct(
        protected UnitService $unitService
    ) {}

    /** List units for a property */
    public function index(Request $request, Property $property)
    {
        Gate::authorize('view', $property);

        $units = $this->unitService->paginate(
            $property,
            filters: $request->only([
                'is_available',
                'unit_type',
                'min_rent_price',
                'max_rent_price',
                'bedrooms',
                'bathrooms',
            ]),
            perPage: (int) $request->input('per_page', 15)
        );

        return UnitResource::collection($units);
    }

    /** Create a unit */
    public function store(Property $property, UnitInsertRequest $request)
    {
        Gate::authorize('update', $property);

        $unit = $this->unitService->create(
            $property,
            UnitDTO::fromRequest($request)
        );

        return new UnitResource($unit);
    }

    /** Show a unit */
    public function show(Property $property, Unit $unit)
    {
        Gate::authorize('view', $property);

        $this->ensureUnitBelongsToProperty($property, $unit);

        return new UnitResource(
            $unit->load('property')
        );
    }

    /** Update a unit */
    public function update(
        Property $property,
        UnitUpdateRequest $request,
        Unit $unit
    ) {
        Gate::authorize('update', $property);

        $this->ensureUnitBelongsToProperty($property, $unit);

        $updated = $this->unitService->update(
            $unit,
            UnitDTO::fromRequest($request, $unit)
        );

        return new UnitResource($updated);
    }

    /** Delete a unit */
    public function destroy(Property $property, Unit $unit)
    {
        Gate::authorize('update', $property);

        $this->ensureUnitBelongsToProperty($property, $unit);

        $this->unitService->delete($unit);

        return response()->noContent();
    }

    private function ensureUnitBelongsToProperty(Property $property, Unit $unit): void
    {
        if ($unit->property_id !== $property->id) {
            abort(404);
        }
    }
}
