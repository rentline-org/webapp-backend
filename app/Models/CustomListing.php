<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Listing|null $listing
 * @property-read Collection<int, Property> $properties
 * @property-read int|null $properties_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomListing newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomListing newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomListing query()
 *
 * @mixin \Eloquent
 */
class CustomListing extends Model
{
    protected $table = 'custom_listings';

    protected $fillable = [
        'listing_id',
        'subdomain',
        'headline',
        'is_published',
        'use_organization_defaults',
        'show_contact_form',
        'show_phone',
        'show_email',
        'contact_email',
        'contact_phone',
        'languages',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'use_organization_defaults' => 'boolean',
        'show_contact_form' => 'boolean',
        'show_phone' => 'boolean',
        'show_email' => 'boolean',
        'languages' => 'array',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
