<?php

namespace App\Models;

use App\Enums\LeaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lease extends Model
{
    /** @use HasFactory<\Database\Factories\LeaseFactory> */
    use HasFactory;

    protected $fillable = [
        'document_id',
        'tenant_contact_id',
        'property_title_snapshot',
        'unit_name_snapshot',
        'tenant_name_snapshot',
        'tenant_email_snapshot',
        'starts_on',
        'ends_on',
        'rent_amount',
        'currency',
        'security_deposit',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'rent_amount' => 'decimal:2',
            'security_deposit' => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'tenant_contact_id');
    }

    public function status(): LeaseStatus
    {
        $today = today();

        if ($today->lt($this->starts_on)) {
            return LeaseStatus::UPCOMING;
        }

        if ($today->gt($this->ends_on)) {
            return LeaseStatus::EXPIRED;
        }

        return LeaseStatus::ACTIVE;
    }
}
