<?php

namespace App\Models;

use App\Enums\ContactAssignmentRole;
use App\Enums\ContactAssignmentSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\ContactAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'contact_id',
        'property_id',
        'unit_id',
        'lease_id',
        'role',
        'source',
        'is_primary',
        'starts_on',
        'ends_on',
        'ownership_percentage',
    ];

    protected function casts(): array
    {
        return [
            'role' => ContactAssignmentRole::class,
            'source' => ContactAssignmentSource::class,
            'is_primary' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'ownership_percentage' => 'decimal:4',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }
}
