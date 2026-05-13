<?php

namespace App\Traits;

trait HasGallery
{
    protected string $thumbnailDisk = 's3';

    public function registerGalleryCollection(): void
    {
        $this->addMediaCollection($this->galleryCollection)->useDisk($this->thumbnailDisk);
    }

    public function gallery(): array
    {
        return $this->getMedia($this->galleryCollection)
            ->map(fn ($media) => [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'name' => $media->name,
            ])
            ->values()
            ->all();
    }

    public function hasGallery(): bool
    {
        return $this->getMedia($this->galleryCollection)->isNotEmpty();
    }
}
