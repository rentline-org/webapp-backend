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

/**
 * @group Unit Management
 * APIs for managing units
 */
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
                'include_archived',
            ]),
            perPage: (int) $request->input('per_page', 15),
            viewer: $request->user(),
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

        return UnitResource::make($unit);
    }

    /** Show a unit */
    public function show(Property $property, Unit $unit)
    {
        Gate::authorize('view', $property);

        $this->ensureUnitBelongsToProperty($property, $unit);
        Gate::authorize('view', $unit);

        return new UnitResource(
            $unit->load([
                'property.organization:id,timezone',
                'property.contactAssignments.contact',
                'leases.organization:id,timezone',
                'contactAssignments.contact',
            ])
        );
    }

    /** Update a unit */
    public function update(
        Property $property,
        UnitUpdateRequest $request,
        Unit $unit
    ) {
        Gate::authorize('view', $property);

        $this->ensureUnitBelongsToProperty($property, $unit);
        Gate::authorize('update', $unit);

        $updated = $this->unitService->update(
            $unit,
            UnitDTO::fromRequest($request, $unit)
        );

        return new UnitResource($updated);
    }

    /** Delete a unit */
    public function destroy(Property $property, Unit $unit)
    {
        Gate::authorize('view', $property);

        $this->ensureUnitBelongsToProperty($property, $unit);
        Gate::authorize('delete', $unit);

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
