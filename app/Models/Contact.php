<?php

namespace App\Models;

use App\Enums\ContactPersonType;
use App\Enums\ContactIdentityKind;
use App\Enums\ContactTaxIdType;
use App\Models\Scopes\OrganizationScope;
use App\Services\Organization\ActiveOrganizationContext;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'user_id',
        'name',
        'email',
        'phone',
        'type',
        'identity_kind',
        'preferred_locale',
        'tax_id_type',
        'tax_id_encrypted',
        'tax_id_hash',
        'tax_id_last4',
    ];

    protected $hidden = [
        'tax_id_encrypted',
        'tax_id_hash',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContactPersonType::class,
            'identity_kind' => ContactIdentityKind::class,
            'tax_id_type' => ContactTaxIdType::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class)->withTimestamps();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ContactAssignment::class);
    }

    public function leaseParties(): HasMany
    {
        return $this->hasMany(LeaseParty::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class, 'tenant_contact_id');
    }

    public function documentParties(): HasMany
    {
        return $this->hasMany(DocumentParty::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    public function latestInvitation(): HasOne
    {
        return $this->hasOne(OrganizationInvitation::class)->latestOfMany();
    }

    public function maskedTaxId(): ?string
    {
        if ($this->tax_id_last4 === null || $this->tax_id_type === null) {
            return null;
        }

        return match ($this->tax_id_type) {
            ContactTaxIdType::CPF => '***.***.***-'.$this->tax_id_last4,
            ContactTaxIdType::CNPJ => '**.***.***/****-'.$this->tax_id_last4,
        };
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
