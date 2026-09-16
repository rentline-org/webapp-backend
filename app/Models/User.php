<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\MediaCollection;
use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Enums\UserRole;
use App\Services\Organization\ActiveOrganizationContext;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $phone
 * @property \Illuminate\Support\Carbon|null $phone_verified_at
 * @property string $password
 * @property string|null $last_active_device
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property bool $is_active
 * @property string|null $remember_token
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property int|null $last_otp
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property array<array-key, mixed>|null $urls
 * @property \Illuminate\Support\Carbon|null $dob
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \App\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Organization> $organizations
 * @property-read int|null $organizations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User permission($permissions, $without = false)
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static Builder<static>|User users()
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereCreatedBy($value)
 * @method static Builder<static>|User whereDob($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereFirstName($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereIsActive($value)
 * @method static Builder<static>|User whereLastActiveDevice($value)
 * @method static Builder<static>|User whereLastLoginAt($value)
 * @method static Builder<static>|User whereLastName($value)
 * @method static Builder<static>|User whereLastOtp($value)
 * @method static Builder<static>|User whereName($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User wherePhone($value)
 * @method static Builder<static>|User wherePhoneVerifiedAt($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @method static Builder<static>|User whereUpdatedBy($value)
 * @method static Builder<static>|User whereUrls($value)
 * @method static Builder<static>|User withoutPermission($permissions)
 * @method static Builder<static>|User withoutRole($roles, $guard = null)
 * @mixin \Eloquent
 */
class User extends Authenticatable implements HasLocalePreference, HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, InteractsWithMedia, MustVerifyEmail, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'locale',
        'password',
        'urls',
        'dob',
        'phone',
        'is_active',
        'created_by',
        'updated_by',
        'last_login_at',
        'email_verified_at',
        'phone_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** defining media/file collection */
    public function registerMediaCollections(): void
    {
        // profile photos
        $this->addMediaCollection(MediaCollection::PROFILE->value)->useDisk('s3')->singleFile();
    }

    public function isSuperAdmin(): bool
    {
        return $this->id == UserRole::SUPER_ADMIN->id() && $this->hasRole(UserRole::SUPER_ADMIN);
    }

    public function isLandlord(): bool
    {
        return $this->hasRole('landlord');
    }

    public function isTenant(): bool
    {
        return ! $this->hasRole([UserRole::LANDLORD, UserRole::SUPER_ADMIN]); // Everyone is user except super admin & landlord
    }

    public function scopeUsers(Builder $query): Builder
    {
        return $query->whereHas('roles', function ($roleQuery) {
            $roleQuery->whereNotIn('name', [
                UserRole::LANDLORD->value,
                UserRole::SUPER_ADMIN->value,
            ]);
        });
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['role', 'status', 'invited_by', 'accepted_at'])
            ->withTimestamps();
    }

    public function sentOrganizationInvitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class, 'invited_by');
    }

    public function preferredLocale(): string
    {
        return in_array($this->locale, config('app.supported_locales', ['en']), true)
            ? $this->locale
            : config('app.fallback_locale', 'en');
    }

    public function membershipRole(?int $organizationId = null): ?OrganizationMemberRole
    {
        if ($this->isSuperAdmin()) {
            return OrganizationMemberRole::OWNER;
        }

        $organizationId ??= app(ActiveOrganizationContext::class)->id();

        if ($organizationId === null) {
            return null;
        }

        $membership = $this->organizations()
            ->whereKey($organizationId)
            ->wherePivot('status', OrganizationMemberStatus::ACTIVE->value)
            ->first()?->pivot;

        if ($membership === null) {
            return null;
        }

        $role = OrganizationMemberRole::tryFrom((string) $membership->role);

        // Existing tenant records predate organization roles and receive the
        // pivot default during migration. Preserve their restricted access.
        if (
            $role === OrganizationMemberRole::MANAGER
            && $this->hasRole(UserRole::TENANT)
            && ! $this->hasRole(UserRole::LANDLORD)
        ) {
            return OrganizationMemberRole::TENANT;
        }

        return $role;
    }

    /** @param list<OrganizationMemberRole|string> $roles */
    public function hasActiveOrganizationRole(array $roles, ?int $organizationId = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $membershipRole = $this->membershipRole($organizationId);
        $allowedRoles = array_map(
            fn (OrganizationMemberRole|string $role): string => $role instanceof OrganizationMemberRole ? $role->value : $role,
            $roles,
        );

        return $membershipRole !== null && in_array($membershipRole->value, $allowedRoles, true);
    }

    public function canManageActiveOrganization(?int $organizationId = null): bool
    {
        return $this->hasActiveOrganizationRole(
            OrganizationMemberRole::administrativeRoles(),
            $organizationId,
        );
    }

    public function canOperateActiveOrganization(?int $organizationId = null): bool
    {
        return $this->hasActiveOrganizationRole(
            OrganizationMemberRole::operationalRoles(),
            $organizationId,
        );
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    public function signedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'signed_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'dob' => 'date',
            'urls' => 'array',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
