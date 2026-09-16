<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaseAmendment extends Model
{
    /** @use HasFactory<\Database\Factories\LeaseAmendmentFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'lease_id',
        'created_by',
        'activated_by',
        'status',
        'effective_on',
        'summary',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_on' => 'date',
            'activated_at' => 'datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(LeaseFinancialTerm::class);
    }
}
