<?php

namespace App\Models;

use App\Enums\ListingType;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property ListingType $type
 * @property-read CustomListing|null $customListing
 * @property-read Organization|null $organization
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Listing newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Listing newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Listing query()
 *
 * @mixin \Eloquent
 */
class Listing extends Model
{
    protected $fillable = [
        'organization_id',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customListing(): HasOne
    {
        return $this->hasOne(CustomListing::class);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope);
    }
}
