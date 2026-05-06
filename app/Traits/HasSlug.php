<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasSlug
{
    /** Boot the trait. */
    protected static function bootHasSlug(): void
    {
        /** @var Model $model */
        static::saving(function (Model $model) {

            $slugField = $model->slugField ?? 'name';
            $uniqueBy = $model->slugUniqueBy ?? null;

            if (! $model->isDirty($slugField)) {
                return;
            }

            $baseSlug = Str::slug($model->{$slugField} ?? '');
            if (empty($baseSlug)) {
                return;
            }

            $slug = $baseSlug;
            $count = 1;

            while (self::slugExists($model, $slug, $uniqueBy)) {
                $slug = "{$baseSlug}-{$count}";
                $count++;
            }

            $model->slug = $slug;
        });
    }

    protected static function slugExists(Model $model, string $slug, ?string $uniqueBy): bool
    {
        $query = static::query()->where('slug', $slug);

        if ($uniqueBy && $model->{$uniqueBy} !== null) {
            $query->where($uniqueBy, $model->{$uniqueBy});
        }

        if ($model->exists && $model->id !== null) {
            $query->where('id', '!=', $model->id);
        }

        return $query->exists();
    }
}
