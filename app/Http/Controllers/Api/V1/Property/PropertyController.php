<?php

namespace App\Http\Controllers\Api\V1\Property;

use App\DTOs\Property\PropertyDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\PropertyInsertUpdateRequest;
use App\Http\Resources\Property\PropertyResource;
use App\Models\Property;
use App\Services\Property\PropertyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PropertyController extends Controller
{
    public function __construct(
        protected PropertyService $propertyService
    ) {
        //
    }

    /** Display a listing of the resource. */
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
            'min_rent_price',
            'max_rent_price',
            'with_units',
        ]);

        $properties = $this->propertyService->all(
            filters: $filters,
        );

        return PropertyResource::collection($properties);

    }

    /** Store a newly created resource in storage. */
    public function store(PropertyInsertUpdateRequest $request)
    {
        Gate::authorize('create', Property::class);

        $request->validated();
        $dto = PropertyDTO::fromRequest($request);

        $property = $this->propertyService->create($dto);

        return PropertyResource::make($property);

    }

    /** Display the specified resource. */
    public function show(Property $property)
    {
        Gate::authorize('view', $property);

        $property->load('units', 'organization');

        return PropertyResource::make($property);
    }

    /** Update the specified resource in storage. */
    public function update(PropertyInsertUpdateRequest $request, Property $property)
    {
        Gate::authorize('update', $property);

        $request->validated();
        $dto = PropertyDTO::fromRequest($request, $property);

        $updatedProperty = $this->propertyService->update($property, $dto);

        return PropertyResource::make($updatedProperty);
    }

    /** Remove the specified resource from storage. */
    public function destroy(Property $property)
    {
        Gate::authorize('delete', $property);

        $this->propertyService->delete($property);

        return $this->respond(['message' => 'Property deleted successfully.'], Response::HTTP_NO_CONTENT);
    }
}
