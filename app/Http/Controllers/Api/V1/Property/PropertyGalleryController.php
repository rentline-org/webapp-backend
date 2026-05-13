<?php

namespace App\Http\Controllers\Api\V1\Property;

use App\Enums\PropertyType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\GalleryInsertRequest;
use App\Http\Requests\Media\GalleryUpdateRequest;
use App\Http\Resources\Property\PropertyResource;
use App\Models\Media;
use App\Models\Property;
use App\Services\Media\GalleryService;

class PropertyGalleryController extends Controller
{
    private string $wrongPropertyTypeMessage = 'Invalid property. Must be multi-unit to have an establishment thumbnail';

    public function __construct(protected GalleryService $galleryService) {}

    public function store(GalleryInsertRequest $request, Property $property)
    {
        $this->checkIsValidProperty($property->property_type);

        $validated = $request->validated();
        $property = $this->galleryService->storeImagesInGallery(
            $property,
            $validated['images'],
            $validated['names']
        );

        return $this->respond([
            'property' => PropertyResource::make($property),
            'message' => 'Image gallery updated',
        ]);
    }

    public function update(GalleryUpdateRequest $request, Property $property, Media $media)
    {
        $this->checkIsValidProperty($property->property_type);

        $validated = $request->validated();
        $property = $this->galleryService->updateGalleryImageName($property, $media->id, $validated['name']);

        return $this->respond([
            'property' => PropertyResource::make($property),
            'message' => 'Image name updated successfully',
        ]);
    }

    public function destroy(Property $property, Media $media)
    {
        $this->checkIsValidProperty($property->property_type);

        $property = $this->galleryService->deleteGalleryImage($property, $media->id);

        return $this->respond([
            'message' => 'Gallery image deleted',
            'property' => PropertyResource::make($property),
        ]);
    }

    public function clear(Property $property)
    {
        $this->checkIsValidProperty($property->property_type);

        $property = $this->galleryService->clearGallery($property);

        return $this->respond([
            'message' => 'Gallery image deleted',
            'property' => PropertyResource::make($property),
        ]);
    }

    private function checkIsValidProperty(PropertyType $type)
    {
        abort_unless($type === PropertyType::MULTI_UNIT, 0, $this->wrongPropertyTypeMessage);
    }
}
