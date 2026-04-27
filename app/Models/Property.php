<?php

namespace App\Models;

use App\Enums\PropertyType;
use App\Helpers\OrganizationHelper;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
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
        'sale_types',
    ];

    protected $casts = [
        'property_type' => PropertyType::class,
        'is_available' => 'boolean',
        'is_furnished' => 'boolean',
        'is_pet_friendly' => 'boolean',
        'rent_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'square_feet' => 'decimal:2',
        'amenities' => 'array',
        'sale_types' => 'array',
        'available_from' => 'date',
    ];

    public function units()
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

    public function hasUnits(): bool
    {
        return $this->units()->exists();
    }

    public function isSingleUnit(): bool
    {
        return ! $this->hasUnits();
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model) {
            if (! $model->organization_id) {
                $model->organization_id = app(OrganizationHelper::class)->get();
            }
        });
    }
}
