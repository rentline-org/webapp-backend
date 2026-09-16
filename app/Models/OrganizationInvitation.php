<?php

namespace App\Models;

use App\Enums\OrganizationMemberRole;
use Database\Factories\OrganizationInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationInvitation extends Model
{
    /** @use HasFactory<OrganizationInvitationFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'contact_id',
        'invited_by',
        'email',
        'role',
        'locale',
        'token_hash',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    protected $hidden = ['token_hash'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    public function status(): string
    {
        if ($this->accepted_at !== null) {
            return 'accepted';
        }

        if ($this->revoked_at !== null) {
            return 'revoked';
        }

        return $this->expires_at->isPast() ? 'expired' : 'pending';
    }

    protected function casts(): array
    {
        return [
            'role' => OrganizationMemberRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
