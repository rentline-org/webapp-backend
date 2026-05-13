<?php

namespace App\Services\Media;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

class GalleryService
{
    /** Delete a single image from gallery. */
    public function deleteGalleryImage(HasMedia&Model $model, int $mediaId): Model
    {
        $media = $model->media()
            ->where('collection_name', $model->galleryCollection)
            ->findOrFail($mediaId);

        $media->delete();

        return $model->load('media');
    }

    /** Clear entire gallery collection. */
    public function clearGallery(HasMedia&Model $model): Model
    {
        $model->clearMediaCollection($model->galleryCollection);

        return $model->load('media');
    }

    /** Update gallery image name. */
    public function updateGalleryImageName(
        HasMedia&Model $model,
        int $mediaId,
        string $name
    ): Model {
        $media = $model->media()
            ->where('collection_name', $model->galleryCollection)
            ->findOrFail($mediaId);

        $media->name = $name;
        $media->save();

        return $model->load('media');
    }

    /**
     * Store images in gallery collection.
     *
     * Expected format:
     * [
     *   [
     *     'file' => UploadedFile,
     *     'name' => 'Kitchen',
     *   ]
     * ]
     */
    public function storeImagesInGallery(
        HasMedia&Model $model,
        array $validatedImages,
        array $validatedNames
    ): Model {
        $images = collect($validatedImages)->map(fn ($image, $index) => [
            'file' => $image,
            'name' => $validatedNames[$index] ?? pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME),
        ])->all();

        foreach ($images as $image) {
            $model->addMedia($image['file'])
                ->usingName($image['name'])
                ->toMediaCollection($model->galleryCollection);
        }

        return $model->load('media');
    }
}
