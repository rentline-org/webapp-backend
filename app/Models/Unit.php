<?php

namespace App\Models;

use App\Enums\MediaCollection;
use App\Enums\PropertyType;
use App\Enums\UnitType;
use App\Traits\HasGallery;
use App\Traits\HasSlug;
use App\Traits\HasThumbnail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $property_id
 * @property string $name
 * @property string|null $description
 * @property UnitType $unit_type
 * @property bool $is_available
 * @property bool $is_furnished
 * @property bool $is_pet_friendly
 * @property numeric|null $rent_price
 * @property numeric|null $sale_price
 * @property numeric|null $buy_price
 * @property int|null $bedrooms
 * @property int|null $bathrooms
 * @property numeric|null $square_feet
 * @property array<array-key, mixed>|null $amenities
 * @property array<array-key, mixed>|null $sale_types
 * @property \Illuminate\Support\Carbon|null $available_from
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $slug
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \App\Models\Property $property
 * @method static \Database\Factories\UnitFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereAmenities($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereAvailableFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereBathrooms($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereBedrooms($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereBuyPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereIsAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereIsFurnished($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereIsPetFriendly($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit wherePropertyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereRentPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereSalePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereSaleTypes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereSquareFeet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereUnitType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Unit whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Unit extends Model implements HasMedia
{
    use HasFactory, HasGallery, HasSlug, HasThumbnail, InteractsWithMedia;
    public string $thumbnailCollection = MediaCollection::UNIT_THUMB->value;
    public string $galleryCollection = MediaCollection::UNIT_GALLERY->value;

    protected $slugField = 'name';
    protected $slugUniqueBy = 'property_id';

    protected $fillable = [
        'property_id',
        'name',
        'description',
        'unit_type',
        'is_available',
        'is_furnished',
        'is_pet_friendly',

        'rent_price',
        'sale_price',
        'buy_price',

        'bedrooms',
        'bathrooms',
        'square_feet',

        'amenities',
        'sale_types',

        'available_from',
    ];

    protected $casts = [
        'unit_type' => UnitType::class,

        'is_available' => 'boolean',
        'is_furnished' => 'boolean',
        'is_pet_friendly' => 'boolean',

        'rent_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'buy_price' => 'decimal:2',

        'square_feet' => 'decimal:2',

        'amenities' => 'array',
        'sale_types' => 'array',

        'available_from' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function registerMediaCollections(): void
    {
        $this->registerThumbnailCollection();
        $this->registerGalleryCollection();
    }

    protected static function booted(): void
    {

        static::deleting(function (Unit $unit) {
            $property = $unit->property;

            if ($property && $property->property_type !== PropertyType::MULTI_UNIT && $property->units()->count() <= 1) {
                throw new \Exception('A property must have at least one unit.');
            }
        });
    }
}
