<?php

namespace App\Models;

use App\Enums\PropertyType;
use App\Enums\UnitType;
use App\Helpers\OrganizationHelper;
use App\Models\Scopes\OrganizationScope;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Property extends Model
{
    use HasFactory, HasSlug;

    protected $slugField = 'title';

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

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model) {
            if (! $model->organization_id) {
                $model->organization_id = app(OrganizationHelper::class)->get();
            }
        });

        // static::saving(function ($property) {
        //     if ($property->isDirty('title')) {
        //         $baseSlug = Str::slug($property->title);
        //         $slug = $baseSlug;
        //         $count = 1;

        //         while (
        //             static::query()
        //                 ->where('slug', $slug)
        //                 ->where('id', '!=', $property->id)
        //                 ->exists()
        //         ) {
        //             $slug = "{$baseSlug}-{$count}";
        //             $count++;
        //         }

        //         $property->slug = $slug;
        //     }
        // });

        static::created(function (Property $property) {
            if ($property->units()->exists()) {
                return;
            }

            // if ($property->property_type === PropertyType::SINGLE_UNIT) {
            //     $property->units()->create([
            //         'name' => $property->title,
            //         'unit_type' => UnitType::HOUSE,
            //     ]);
            // }

            if ($property->property_type === PropertyType::LAND) {
                $property->units()->create([
                    'name' => $property->title,
                    'unit_type' => UnitType::LAND,
                ]);
            }
        });
    }
}
