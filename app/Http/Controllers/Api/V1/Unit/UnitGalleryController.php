<?php

namespace App\Http\Controllers\Api\V1\Unit;

use App\Enums\MediaCollection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\GalleryInsertRequest;
use App\Http\Requests\Media\GalleryUpdateRequest;
use App\Http\Resources\Unit\UnitResource;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Media\GalleryService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class UnitGalleryController extends Controller
{
    public function __construct(protected GalleryService $galleryService) {}

    public function storeGallery(GalleryInsertRequest $request, Property $property, Unit $unit)
    {
        abort_unless($unit->property_id === $property->id, 404);

        $validated = $request->validated();

        $unit = $this->galleryService->storeImagesInGallery(
            $unit,
            $validated['images'],
            $validated['names']
        );

        return $this->respond([
            'message' => 'Gallery images uploaded successfully.',
            'unit' => UnitResource::make($unit),
        ], Response::HTTP_CREATED);
    }

    public function updateGalleryImageName(GalleryUpdateRequest $request, Property $property, Unit $unit, Media $media)
    {
        abort_unless($unit->property_id === $property->id, 404);

        $validated = $request->validated();

        $unit = $this->galleryService->updateGalleryImageName(
            $unit,
            $media->id,
            $validated['name']
        );

        return $this->respond([
            'message' => 'Gallery image name updated',
            'unit' => UnitResource::make($unit),
        ]);
    }

    public function destroyGalleryImage(Property $property, Unit $unit, Media $media)
    {
        abort_unless($unit->property_id === $property->id, 404);

        abort_unless(
            $media->model_id === $unit->id &&
            $media->model_type === Unit::class &&
            $media->collection_name === MediaCollection::UNIT_GALLERY->value,
            404
        );

        $unit = $this->galleryService->deleteGalleryImage($unit, $media->id);

        return $this->respond([
            'message' => 'Gallery image deleted successfully.',
            'unit' => UnitResource::make($unit->load('property')),
        ], Response::HTTP_OK);
    }

    public function clearGallery(Property $property, Unit $unit)
    {
        abort_unless($unit->property_id === $property->id, 404);

        $unit = $this->galleryService->clearGallery($unit);

        return $this->respond([
            'message' => 'Gallery cleared successfully.',
            'unit' => UnitResource::make($unit->load('property')),
        ]);
    }
}
