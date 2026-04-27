<?php

namespace App\Models;

use App\Enums\UnitType;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'property_id',
        'name',
        'description',
        'unit_type',
        'is_available',
        'is_furnished',
        'rent_price',
        'sale_price',
        'bedrooms',
        'bathrooms',
        'square_feet',
        'amenities',
        'available_from',
        'is_pet_friendly',
    ];

    protected $casts = [
        'unit_type' => UnitType::class,
        'is_available' => 'boolean',
        'is_furnished' => 'boolean',
        'is_pet_friendly' => 'boolean',
        'rent_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'square_feet' => 'decimal:2',
        'amenities' => 'array',
        'available_from' => 'date',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    protected static function booted(): void
    {
        static::creating(function ($unit) {
            if (! $unit->property) {
                return;
            }

            if (in_array($unit->property->property_type->value, ['house', 'land'])) {
                throw new \Exception('This property type cannot have units.');
            }
        });
    }
}
