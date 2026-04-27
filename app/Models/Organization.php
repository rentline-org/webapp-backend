<?php

namespace App\Models;

use App\Enums\OrganizationPlan;
use App\Enums\TaxIDType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Organization extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'phone',
        'email',
        'website',
        'owner_id',
        'country',
        'state',
        'city',
        'postal_code',
        'address_line',
        'currency',
        'timezone',
        'tax_id',
        'tax_id_type',
        'plan',
        'is_plan_active',
        'data_retention_until',
        'is_active',
        'settings',
        'trial_ends_at',
    ];

    protected $casts = [
        'is_plan_active' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array',
        'data_retention_until' => 'datetime',
        'trial_ends_at' => 'datetime',
        'tax_id_type' => TaxIDType::class,
        'plan' => OrganizationPlan::class,
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
    }
}
