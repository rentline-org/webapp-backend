<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;

class Organization extends Model
{
    use InteractsWithMedia;

    protected $fillable = [
        'title',
        'description',
        'address',
        'phone',
        'email',
        'website',
        'number_of_properties',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile(); // ensures only 1 avatar
    }
}
