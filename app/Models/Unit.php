<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    protected $fillable = [
        'name',
        'description',
        'floor',
        'bedrooms',
        'bathrooms',
        'square_feet',
        'rent_price',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
