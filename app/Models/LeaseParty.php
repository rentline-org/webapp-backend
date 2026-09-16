<?php

namespace App\Models;

use App\Enums\LeasePartyRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseParty extends Model
{
    /** @use HasFactory<\Database\Factories\LeasePartyFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'lease_id',
        'contact_id',
        'role',
        'is_primary',
        'name_snapshot',
        'email_snapshot',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'role' => LeasePartyRole::class,
            'is_primary' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
