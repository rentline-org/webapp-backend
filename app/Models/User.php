<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\MediaCollection;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, InteractsWithMedia, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'user_name',
        'name',
        'email',
        'password',
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
        $this->addMediaCollection(MediaCollection::PROFILE->value)->singleFile();
    }

    public function isSuperAdmin(): bool
    {
        return $this->id == UserRole::SUPER_ADMIN->id() && $this->hasRole(UserRole::SUPER_ADMIN);
    }

    public function isLandlord(): bool
    {
        return $this->hasRole(UserRole::LANDLORD);
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

    public function organizations()
    {
        return $this->belongsToMany(Organization::class)->withTimestamps();
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
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
