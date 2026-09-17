<?php

namespace App\Models;

use App\Enums\ActionItemStatus;
use App\Enums\ActionItemType;
use Database\Factories\ActionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionItem extends Model
{
    /** @use HasFactory<ActionItemFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'lease_id',
        'document_id',
        'property_id',
        'unit_id',
        'assigned_to',
        'type',
        'status',
        'priority',
        'title',
        'description',
        'due_on',
        'unique_key',
        'metadata',
        'resolved_at',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    protected function casts(): array
    {
        return [
            'type' => ActionItemType::class,
            'status' => ActionItemStatus::class,
            'due_on' => 'date',
            'metadata' => 'array',
            'resolved_at' => 'datetime',
        ];
    }
}
