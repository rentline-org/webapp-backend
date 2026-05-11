<?php

namespace App\Http\Controllers\Api\V1\Unit;

use App\Enums\MediaCollection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Unit\UnitResource;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Unit\UnitGalleryService;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class UnitMediaController extends Controller
{
    public function __construct(protected UnitGalleryService $unitGalleryService) {}

    public function storeGallery(Request $request, Property $property, Unit $unit)
    {
        abort_unless($unit->property_id === $property->id, 404);

        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['file', 'image', 'max:2048'],
            'names' => ['nullable', 'array'],
            'names.*' => ['nullable', 'string', 'max:255'],
        ]);

        $images = collect($validated['images'])->map(fn ($image, $index) => [
            'file' => $image,
            'name' => $validated['names'][$index] ?? pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME),
        ])->all();

        $unit = $this->unitGalleryService->storeImagesInGallery($unit, $images);

        return $this->respond([
            'message' => 'Gallery images uploaded successfully.',
            'unit' => UnitResource::make($unit),
        ], Response::HTTP_CREATED);
    }

    public function storeThumbnail(Request $request, Property $property, Unit $unit)
    {
        abort_unless($unit->property_id === $property->id, 404);

        $validated = $request->validate([
            'thumbnail' => ['required', 'file', 'image', 'max:2048'],
        ]);

        $unit = $this->unitGalleryService->storeThumbnail(
            $unit,
            $validated['thumbnail']
        );

        return $this->respond([
            'message' => 'Thumbnail uploaded successfully.',
            'unit' => UnitResource::make($unit->load('property')),
        ], Response::HTTP_CREATED);
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

        $unit = $this->unitGalleryService->deleteGalleryImage($unit, $media->id);

        return $this->respond([
            'message' => 'Gallery image deleted successfully.',
            'unit' => UnitResource::make($unit->load('property')),
        ], Response::HTTP_OK);
    }

    public function destroyThumbnail(Property $property, Unit $unit)
    {
        abort_unless($unit->property_id === $property->id, 404);

        $unit = $this->unitGalleryService->deleteThumbnail($unit);

        return $this->respond([
            'message' => 'Thumbnail deleted successfully.',
            'unit' => UnitResource::make($unit->load('property')),
        ]);
    }

    public function clearGallery(Property $property, Unit $unit)
    {
        abort_unless($unit->property_id === $property->id, 404);

        $unit = $this->unitGalleryService->clearGallery($unit);

        return $this->respond([
            'message' => 'Gallery cleared successfully.',
            'unit' => UnitResource::make($unit->load('property')),
        ]);
    }
}
