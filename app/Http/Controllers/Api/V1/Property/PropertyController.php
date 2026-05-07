<?php

namespace App\Http\Controllers\Api\V1\Property;

use App\DTOs\Property\PropertyDTO;
use App\DTOs\Unit\UnitDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\PropertyInsertRequest;
use App\Http\Requests\Property\PropertyUpdateRequest;
use App\Http\Resources\Property\PropertyResource;
use App\Models\Property;
use App\Services\Property\PropertyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PropertyController extends Controller
{
    public function __construct(
        protected PropertyService $propertyService
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Property::class);

        $filters = $request->only([
            'search',
            'property_type',
            'is_available',
            'city',
            'state',
            'country',
            'with_units',
        ]);

        // 🚨 price filtering is now UNIT responsibility
        if ($request->filled('min_rent_price')) {
            $filters['min_rent_price'] = $request->input('min_rent_price');
        }

        if ($request->filled('max_rent_price')) {
            $filters['max_rent_price'] = $request->input('max_rent_price');
        }

        $properties = $this->propertyService->all($filters);

        return PropertyResource::collection($properties);
    }

    public function store(PropertyInsertRequest $request)
    {
        Gate::authorize('create', Property::class);

        $validated = $request->validated();

        $units = $validated['units'] ?? [];
        unset($validated['units']);

        $property = $this->propertyService->create(
            PropertyDTO::fromArray($validated),
            collect($units)
                ->map(fn ($unit) => UnitDTO::fromArray($unit, null))
                ->all()
        );

        return PropertyResource::make(
            $property->loadCount('units')
        );
    }

    public function show(Property $property)
    {
        Gate::authorize('view', $property);

        return PropertyResource::make(
            $property->load(['units', 'organization'])->loadCount('units')
        );
    }

    public function showBySlug(string $slug)
    {
        $property = $this->propertyService->findBySlug($slug);

        Gate::authorize('view', $property);

        return PropertyResource::make(
            $property->load(['units', 'organization'])->loadCount('units')
        );
    }

    public function update(PropertyUpdateRequest $request, Property $property)
    {
        Gate::authorize('update', $property);

        $updatedProperty = $this->propertyService->update(
            $property,
            PropertyDTO::fromRequest($request, $property)
        );

        return PropertyResource::make(
            $updatedProperty->loadCount('units')
        );
    }

    public function destroy(Property $property)
    {
        Gate::authorize('delete', $property);

        $this->propertyService->delete($property);

        return response()->noContent();
    }
}
