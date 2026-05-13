<?php

namespace App\Models;

use App\Enums\MediaCollection;
use App\Enums\PropertyType;
use App\Enums\UnitType;
use App\Helpers\OrganizationHelper;
use App\Models\Scopes\OrganizationScope;
use App\Traits\HasGallery;
use App\Traits\HasSlug;
use App\Traits\HasThumbnail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Property extends Model implements HasMedia
{
    use HasFactory, HasGallery, HasSlug, HasThumbnail, InteractsWithMedia;

    protected $slugField = 'title';
    protected string $thumbnailCollection = MediaCollection::PROPERTY_THUMB->value;
    protected string $galleryCollection = MediaCollection::PROPERTY_GALLERY->value;

    protected $fillable = [
        'organization_id',
        'slug',
        'title',
        'description',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'property_type',
    ];

    protected $casts = [
        'property_type' => PropertyType::class,
    ];

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    /** Always at least 1 unit */
    public function hasUnits(): bool
    {
        return $this->units()->exists();
    }

    /** For UI convenience */
    public function primaryUnit(): ?Unit
    {
        return $this->units()->first();
    }

    public function isSingleUnit(): bool
    {
        return $this->property_type == PropertyType::SINGLE_UNIT;
    }

    public function isMultiUnit(): bool
    {
        return $this->property_type == PropertyType::MULTI_UNIT;
    }

    public function registerMediaCollections(): void
    {
        $this->registerThumbnailCollection();
        $this->registerGalleryCollection();
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model) {
            if (! $model->organization_id) {
                $model->organization_id = app(OrganizationHelper::class)->get();
            }
        });

        static::created(function (Property $property) {
            if ($property->units()->exists()) {
                return;
            }

            if ($property->property_type === PropertyType::LAND) {
                $property->units()->create([
                    'name' => $property->title,
                    'unit_type' => UnitType::LAND,
                ]);
            }
        });
    }
}
