<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentParty extends Model
{
    protected $fillable = [
        'document_id',
        'contact_id',
        'role',
        'name_snapshot',
        'email_snapshot',
        'is_primary',
        'ownership_percentage',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'ownership_percentage' => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
