<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\MediaCollection;
use App\Models\Scopes\OrganizationScope;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Document extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'organization_id',
        'property_id',
        'unit_id',
        'uploaded_by',
        'signed_by',
        'type',
        'title',
        'purpose',
        'description',
        'requires_signature',
        'is_signed',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'requires_signature' => 'boolean',
            'is_signed' => 'boolean',
            'signed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function lease(): HasOne
    {
        return $this->hasOne(Lease::class);
    }

    public function registerMediaCollections(): void
    {
        $acceptedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'text/plain',
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        $this->addMediaCollection(MediaCollection::DOCUMENT_ORIGINAL->value)
            ->useDisk(config('filesystems.document_disk'))
            ->acceptsMimeTypes($acceptedMimeTypes)
            ->singleFile();

        $this->addMediaCollection(MediaCollection::DOCUMENT_SIGNED->value)
            ->useDisk(config('filesystems.document_disk'))
            ->acceptsMimeTypes($acceptedMimeTypes)
            ->singleFile();
    }

    /**
     * Keep route binding tenant-scoped even though the active organization
     * middleware runs after Laravel's binding middleware in this application.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);
        $organizationId = app(ActiveOrganizationContext::class)->id();

        if ($organizationId === null) {
            $headerOrganizationId = filter_var(
                request()->header('X-Organization-Id'),
                FILTER_VALIDATE_INT
            );
            $organizationId = $headerOrganizationId === false
                ? null
                : $headerOrganizationId;
        }

        $user = request()->user();

        if (
            $organizationId === null
            || $organizationId < 1
            || $user === null
            || ! $user->organizations()->whereKey($organizationId)->exists()
        ) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(
            $this->qualifyColumn('organization_id'),
            $organizationId
        );
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope);
    }
}
