<?php

namespace App\Traits;

trait HasThumbnail
{
    protected string $thumbnailDisk = 's3';

    public function registerThumbnailCollection(): void
    {
        if (! $this->thumbnailCollection) {
            return;
        }

        $this->addMediaCollection($this->thumbnailCollection)->useDisk($this->thumbnailDisk)->singleFile();
    }

    public function thumbnail(): ?array
    {
        $media = $this->getFirstMedia($this->thumbnailCollection);

        if (! $media) {
            return null;
        }

        return [
            'id' => $media->id,
            'url' => $media->getUrl(),
        ];
    }

    public function hasThumbnail(): bool
    {
        return $this->hasMedia($this->thumbnailCollection);
    }
}
