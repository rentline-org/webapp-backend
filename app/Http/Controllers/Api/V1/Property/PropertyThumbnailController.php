<?php

namespace App\Http\Controllers\Api\V1\Property;

use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\ThumbnailInsertRequest;
use App\Models\Property;
use App\Services\Media\ThumbnailService;

class PropertyThumbnailController extends Controller
{
    private string $wrongPropertyTypeMessage = 'Invalid property. Must be multi-unit to have an establishment thumbnail';

    public function __construct(protected ThumbnailService $thumbnailService) {}

    public function store(ThumbnailInsertRequest $request, Property $property)
    {
        abort_unless($property->property_type === PropertyType::MULTI_UNIT, 422, $this->wrongPropertyTypeMessage);

        $validated_payload = $request->validated();
        $property = $this->thumbnailService->storeThumbnail($property, $validated_payload['thumbnail']);

        return $this->respond([
            'property' => $property,
            'message' => 'Property thumbnail uploaded',
        ]);
    }

    public function destroy(Property $property)
    {
        abort_unless($property->property_type === PropertyType::MULTI_UNIT, 422, $this->wrongPropertyTypeMessage);

        $deletedProperty = $this->thumbnailService->deleteThumbnail($property);
        $this->respond([
            'property' => $deletedProperty,
            'message' => 'Deleted property successfully',
        ]);
    }
}
