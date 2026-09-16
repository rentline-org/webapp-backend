<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentVersion extends Model
{
    protected $fillable = [
        'document_id',
        'version_number',
        'created_by',
        'primary_media_id',
        'signed_media_id',
        'notes',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'finalized_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function primaryMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'primary_media_id');
    }

    public function signedMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'signed_media_id');
    }

    public function signers(): HasMany
    {
        return $this->hasMany(DocumentSigner::class);
    }
}
