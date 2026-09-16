<?php

namespace App\Models;

use App\Enums\MediaCollection;
use App\Enums\PropertyType;
use App\Enums\PropertyOperationalStatus;
use App\Enums\UnitType;
use App\Helpers\OrganizationHelper;
use App\Models\Scopes\OrganizationScope;
use App\Traits\HasGallery;
use App\Traits\HasSlug;
use App\Traits\HasThumbnail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int          $id
 * @property int          $organization_id
 * @property string       $slug
 * @property string       $title
 * @property string|null  $description
 * @property string       $address
 * @property string       $city
 * @property string       $state
 * @property string       $postal_code
 * @property string|null  $country
 * @property PropertyType $property_type
 * @property Carbon|null  $created_at
 * @property Carbon|null  $updated_at
 * @property-read Collection<int, Contact> $contacts
 * @property-read int|null $contacts_count
 * @property-read CustomListing|null $customListing
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Organization|null $organization
 * @property-read Collection<int, Unit> $units
 * @property-read int|null $units_count
 *
 * @method static \Database\Factories\PropertyFactory                    factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereCity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property wherePostalCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property wherePropertyType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Property whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Property extends Model implements HasMedia
{
    use HasFactory, HasGallery, HasSlug, HasThumbnail, InteractsWithMedia;
    public string $thumbnailCollection = MediaCollection::PROPERTY_THUMB->value;
    public string $galleryCollection = MediaCollection::PROPERTY_GALLERY->value;

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
        'longitude',
        'latitude',
        'full_address',
        'address_number',
        'region_code',
        'operational_status',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'operational_status' => PropertyOperationalStatus::class,
            'archived_at' => 'datetime',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customListing(): BelongsTo
    {
        return $this->belongsTo(CustomListing::class);
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class)->withTimestamps();
    }

    public function contactAssignments(): HasMany
    {
        return $this->hasMany(ContactAssignment::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
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

    public function registerMediaCollections(): void
    {
        $this->registerThumbnailCollection();
        $this->registerGalleryCollection();
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model) {
            if (! $model->organization_id) {
                $model->organization_id = app(OrganizationHelper::class)->get();
            }
        });

        static::created(function (Property $property) {
            if ($property->units()->exists()) {
                return;
            }

            if ($property->property_type === PropertyType::LAND) {
                $property->units()->create([
                    'name' => $property->title,
                    'unit_type' => UnitType::LAND,
                ]);
            }
        });
    }
}
