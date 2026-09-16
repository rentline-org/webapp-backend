<?php

namespace App\Models;

use App\Enums\DocumentSignerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSigner extends Model
{
    protected $fillable = [
        'document_id',
        'document_version_id',
        'contact_id',
        'name_snapshot',
        'email_snapshot',
        'role',
        'status',
        'signed_at',
        'acted_by',
        'provider',
        'provider_envelope_id',
        'provider_signer_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentSignerStatus::class,
            'signed_at' => 'datetime',
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

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
