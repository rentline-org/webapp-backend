<?php

namespace App\Models;

use App\Enums\OrganizationMemberStatus;
use App\Models\Scopes\OrganizationScope;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentKind extends Model
{
    protected $fillable = [
        'organization_id',
        'key',
        'label_en',
        'label_pt_br',
        'category',
        'allowed_scopes',
        'supports_expiry',
        'default_requires_signature',
        'is_active',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function localizedLabel(?string $locale = null): string
    {
        return str_starts_with($locale ?? app()->getLocale(), 'pt')
            ? $this->label_pt_br
            : $this->label_en;
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);
        $organizationId = app(ActiveOrganizationContext::class)->id();

        if ($organizationId === null) {
            $headerOrganizationId = filter_var(
                request()->header('X-Organization-Id'),
                FILTER_VALIDATE_INT,
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
            || (! $user->isSuperAdmin() && ! $user->organizations()
                ->whereKey($organizationId)
                ->wherePivot('status', OrganizationMemberStatus::ACTIVE->value)
                ->exists())
        ) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->qualifyColumn('organization_id'), $organizationId);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope);
    }

    protected function casts(): array
    {
        return [
            'allowed_scopes' => 'array',
            'supports_expiry' => 'boolean',
            'default_requires_signature' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
