<?php

namespace App\Services\Media;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

class ThumbnailService
{
    /** Delete thumbnail from model. */
    public function deleteThumbnail(HasMedia&Model $model): Model
    {
        $model->clearMediaCollection($model->thumbnailCollection);

        return $model->load(['media']);
    }

    /** Store thumbnail for model. */
    public function storeThumbnail(
        HasMedia&Model $model,
        mixed $thumbnail
    ): Model {
        $model->addMedia($thumbnail)
            ->toMediaCollection($model->thumbnailCollection);

        return $model->load(['media']);
    }
}
