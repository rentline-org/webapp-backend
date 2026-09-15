<?php

namespace App\Models;

use App\Enums\ContactPersonType;
use App\Models\Scopes\OrganizationScope;
use App\Services\Organization\ActiveOrganizationContext;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int               $id
 * @property int               $organization_id
 * @property string            $name
 * @property string|null       $email
 * @property string|null       $phone
 * @property ContactPersonType $type
 * @property Carbon|null       $created_at
 * @property Carbon|null       $updated_at
 * @property-read Organization|null $organization
 * @property-read Collection<int, Property> $properties
 * @property-read int|null $properties_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'phone',
        'type',
    ];

    protected $casts = [
        'type' => ContactPersonType::class,
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class)->withTimestamps();
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_contact_id');
    }

    /**
     * Keep implicit route binding inside an organization even though the
     * active-organization middleware runs after Laravel's binding middleware.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);
        $organizationId = app(ActiveOrganizationContext::class)->id();

        if ($organizationId === null) {
            $headerOrganizationId = filter_var(
                request()->header('X-Organization-Id'),
                FILTER_VALIDATE_INT
            );
            $organizationId = $headerOrganizationId === false
                ? null
                : $headerOrganizationId;
        }

        $user = request()->user();

        if (
            $organizationId === null
            || $organizationId < 1
            || $user === null
            || ! $user->organizations()->whereKey($organizationId)->exists()
        ) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(
            $this->qualifyColumn('organization_id'),
            $organizationId
        );
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope);
    }
}
