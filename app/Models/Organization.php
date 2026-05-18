<?php

namespace App\Models;

use App\Enums\MediaCollection;
use App\Enums\OrganizationPlan;
use App\Enums\TaxIDType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int                          $id
 * @property string                       $title
 * @property string|null                  $description
 * @property string|null                  $phone
 * @property string                       $email
 * @property string|null                  $website
 * @property int                          $owner_id
 * @property string                       $country
 * @property string|null                  $state
 * @property string                       $city
 * @property string                       $postal_code
 * @property string                       $address_line
 * @property string                       $currency
 * @property string                       $timezone
 * @property string|null                  $tax_id
 * @property TaxIDType|null               $tax_id_type
 * @property OrganizationPlan             $plan
 * @property bool                         $is_plan_active
 * @property Carbon|null                  $data_retention_until
 * @property bool                         $is_active
 * @property array<array-key, mixed>|null $settings
 * @property Carbon|null                  $trial_ends_at
 * @property Carbon|null                  $deleted_at
 * @property Carbon|null                  $created_at
 * @property Carbon|null                  $updated_at
 * @property-read Collection<int, Contact> $contacts
 * @property-read int|null $contacts_count
 * @property-read Collection<int, Listing> $listings
 * @property-read int|null $listings_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read User $owner
 * @property-read Collection<int, Property> $properties
 * @property-read int|null $properties_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\OrganizationFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereAddressLine($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereDataRetentionUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereIsPlanActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization wherePlan($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization wherePostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereTaxId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereTaxIdType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereTrialEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization whereWebsite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Organization withoutTrashed()
 *
 * @mixin \Eloquent
 */
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

    public function listing(): HasOne
    {
        return $this->hasOne(Listing::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::ORGANIZATION->value)->useDisk('s3')->singleFile();
    }
}
