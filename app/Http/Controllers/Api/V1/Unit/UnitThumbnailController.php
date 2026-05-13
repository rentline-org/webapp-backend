<?php

namespace App\Http\Controllers\Api\V1\Unit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\ThumbnailInsertRequest;
use App\Http\Resources\Unit\UnitResource;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Media\ThumbnailService;
use Symfony\Component\HttpFoundation\Response;

class UnitThumbnailController extends Controller
{
    public function __construct(protected ThumbnailService $thumbnailService) {}

    public function storeThumbnail(ThumbnailInsertRequest $request, Property $property, Unit $unit)
    {
        abort_unless($unit->property_id === $property->id, 404);

        $validated = $request->validated();

        $unit = $this->thumbnailService->storeThumbnail(
            $unit,
            $validated['thumbnail']
        );

        return $this->respond([
            'message' => 'Thumbnail uploaded successfully.',
            'unit' => UnitResource::make($unit->load('property')),
        ], Response::HTTP_CREATED);
    }

    public function destroyThumbnail(Property $property, Unit $unit)
    {
        abort_unless($unit->property_id === $property->id, 404);

        $unit = $this->thumbnailService->deleteThumbnail($unit);

        return $this->respond([
            'message' => 'Thumbnail deleted successfully.',
            'unit' => UnitResource::make($unit->load('property')),
        ]);
    }
}
