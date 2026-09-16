<?php

namespace App\Models;

use App\Enums\LeaseStatus;
use App\Enums\LeaseWorkflowStatus;
use App\Enums\RentalGuaranteeType;
use App\Models\Scopes\OrganizationScope;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lease extends Model
{
    /** @use HasFactory<\Database\Factories\LeaseFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'property_id',
        'unit_id',
        'document_id',
        'renewed_from_id',
        'activated_by',
        'workflow_status',
        'guarantee_type',
        'activated_at',
        'terminated_on',
        'termination_reason',
        'title',
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
            'terminated_on' => 'date',
            'activated_at' => 'datetime',
            'rent_amount' => 'decimal:2',
            'security_deposit' => 'decimal:2',
            'workflow_status' => LeaseWorkflowStatus::class,
            'guarantee_type' => RentalGuaranteeType::class,
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

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'tenant_contact_id');
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'renewed_from_id');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(LeaseParty::class);
    }

    public function financialTerms(): HasMany
    {
        return $this->hasMany(LeaseFinancialTerm::class)->orderBy('effective_from')->orderBy('id');
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(LeaseAmendment::class)->orderByDesc('effective_on')->orderByDesc('id');
    }

    public function contactAssignments(): HasMany
    {
        return $this->hasMany(ContactAssignment::class);
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_lease')
            ->withPivot('relation_type')
            ->withTimestamps();
    }

    public function status(): LeaseStatus
    {
        $today = today();

        if ($this->starts_on === null || $today->lt($this->starts_on)) {
            return LeaseStatus::UPCOMING;
        }

        if ($this->ends_on !== null && $today->gt($this->ends_on)) {
            return LeaseStatus::EXPIRED;
        }

        return LeaseStatus::ACTIVE;
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);
        $organizationId = app(ActiveOrganizationContext::class)->id()
            ?? filter_var(request()->header('X-Organization-Id'), FILTER_VALIDATE_INT);
        $user = request()->user();

        if (! $organizationId || $user === null || ! $user->organizations()->whereKey($organizationId)->exists()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->qualifyColumn('organization_id'), $organizationId);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Lease $lease): void {
            if ($lease->document_id === null) {
                return;
            }

            $document = Document::withoutGlobalScopes()->find($lease->document_id);
            $lease->organization_id ??= $document?->organization_id;
            $lease->property_id ??= $document?->property_id;
            $lease->unit_id ??= $document?->unit_id;
        });
    }
}
