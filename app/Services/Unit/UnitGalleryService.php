<?php

namespace App\Services\Unit;

use App\Enums\MediaCollection;
use App\Models\Unit;

class UnitGalleryService
{
    /**
     * __construct
     *
     * @return void
     */
    public function __construct() {}

    public function deleteGalleryImage(Unit $unit, int $mediaId): Unit
    {
        $media = $unit->media()
            ->where('collection_name', MediaCollection::UNIT_GALLERY->value)
            ->findOrFail($mediaId);

        $media->delete();

        return $unit->load(['media']);
    }

    public function deleteThumbnail(Unit $unit): Unit
    {
        $unit->clearMediaCollection(MediaCollection::UNIT_THUMB->value);

        return $unit->load(['media']);
    }

    public function clearGallery(Unit $unit): Unit
    {
        $unit->clearMediaCollection(MediaCollection::UNIT_GALLERY->value);

        return $unit;
    }

    public function storeImagesInGallery(Unit $unit, array $images)
    {
        foreach ($images as $image) {
            $unit->addMedia($image['file'])
                ->usingName($image['name'])
                ->toMediaCollection(MediaCollection::UNIT_GALLERY->value);
        }

        // $unit->load(['media']);

        return $unit;
    }

    public function storeThumbnail(Unit $unit, $thumbnail)
    {
        $unit->addMedia($thumbnail)->toMediaCollection(MediaCollection::UNIT_THUMB->value);
        $unit->load(['media']);

        return $unit;
    }
}
