<?php

namespace App\Http\Controllers\Api\V1\Unit;

use App\DTOs\Unit\UnitDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\UnitInsertUpdateRequest;
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

    /** List units for a property. */
    public function index(Request $request, Property $property)
    {
        Gate::authorize('view', $property);

        $units = $this->unitService->paginate(
            $property,
            filters: $request->only(['is_available', 'unit_type']),
            perPage: (int) $request->input('per_page', 15)
        );

        return UnitResource::collection($units);
    }

    /** Create a unit under a property. */
    public function store(Property $property, UnitInsertUpdateRequest $request)
    {
        Gate::authorize('update', $property);

        $dto = UnitDTO::fromRequest($request);

        $unit = $this->unitService->create($property, $dto);

        return new UnitResource($unit);
    }

    /** Show a single unit. */
    public function show(Property $property, Unit $unit)
    {
        Gate::authorize('view', $property);

        return new UnitResource(
            $unit->load('property')
        );
    }

    /** Update a unit. */
    public function update(
        Property $property,
        UnitInsertUpdateRequest $request,
        Unit $unit
    ) {
        Gate::authorize('update', $property);

        $dto = UnitDTO::fromRequest($request, $unit);

        $updated = $this->unitService->update($unit, $dto);

        return new UnitResource($updated);
    }

    /** Delete a unit. */
    public function destroy(Property $property, Unit $unit)
    {
        Gate::authorize('update', $property);

        if ($unit->property_id !== $property->id) {
            abort(404);
        }

        $this->unitService->delete($unit);

        return response()->noContent();
    }
}
