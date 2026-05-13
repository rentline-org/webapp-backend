<?php

namespace App\Http\Controllers\Api\V1\Property;

use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\ThumbnailInsertRequest;
use App\Http\Resources\Property\PropertyResource;
use App\Models\Property;
use App\Services\Media\ThumbnailService;
use Symfony\Component\HttpFoundation\Response;

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
            'property' => PropertyResource::make($property),
            'message' => 'Property thumbnail uploaded',
        ], Response::HTTP_CREATED);
    }

    public function destroy(Property $property)
    {
        abort_unless($property->property_type === PropertyType::MULTI_UNIT, 422, $this->wrongPropertyTypeMessage);

        $property = $this->thumbnailService->deleteThumbnail($property);
        $this->respond([
            'property' => PropertyResource::make($property),
            'message' => 'Deleted property successfully',
        ]);
    }
}
