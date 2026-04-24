<?php

namespace App\Models;

use App\Helpers\OrganizationHelper;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'name',
        'description',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'price',
        'property_type',
        'sale_type',
        'is_available',
        'is_furnished',
        'bedrooms',
        'bathrooms',
        'square_feet',
        'amenities',
        'available_from',
        'is_pet_friendly',
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

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model) {
            if (! $model->organization_id) {
                $model->organization_id = OrganizationHelper::currentOrganizationId();
            }
        });
    }
}
