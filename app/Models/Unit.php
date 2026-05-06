<?php

namespace App\Models;

use App\Enums\PropertyType;
use App\Enums\UnitType;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unit extends Model
{
    use HasFactory, HasSlug;

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
