<?php

namespace App\Http\Resources\Lease;

use App\Enums\LeaseFinancialTermType;
use App\Enums\LeasePartyRole;
use App\Enums\LeaseStatus;
use App\Enums\LeaseWorkflowStatus;
use App\Enums\RentalGuaranteeType;
use App\Models\LeaseFinancialTerm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OperationalLeaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canManage = $request->user()?->can('update', $this->resource) ?? false;
        $primaryTenant = $this->relationLoaded('parties')
            ? $this->parties->firstWhere('role', LeasePartyRole::PRIMARY_TENANT)
            : null;
        $presentedTerms = $this->relationLoaded('financialTerms')
            ? $this->presentedFinancialTerms()
            : collect();
        $visibleParties = ! $this->relationLoaded('parties')
            ? collect()
            : ($canManage
                ? $this->parties
                : $this->parties->filter(
                    fn ($party): bool => $party->contact?->user_id === $request->user()?->id
                )->values());
        $visiblePrimaryTenant = ($canManage
            || $primaryTenant?->contact?->user_id === $request->user()?->id)
                ? $primaryTenant
                : null;
        $visibleTerms = $canManage
            ? $presentedTerms
            : $presentedTerms->whereIn('type', [
                LeaseFinancialTermType::RENT,
                LeaseFinancialTermType::SECURITY_DEPOSIT,
                LeaseFinancialTermType::INSURANCE_PREMIUM,
            ])->values();
        $rent = $presentedTerms->firstWhere('type', LeaseFinancialTermType::RENT);
        $deposit = $presentedTerms->firstWhere('type', LeaseFinancialTermType::SECURITY_DEPOSIT);
        $today = $this->organizationToday();
        $isUpcoming = $this->starts_on !== null && $this->starts_on->toDateString() > $today->toDateString();

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'title' => $this->title,
            'workflow_status' => $this->workflow_status?->value ?? $this->workflow_status,
            'temporal_status' => $this->status() === LeaseStatus::ACTIVE ? 'current' : $this->status()->value,
            'property_id' => $this->property_id,
            'unit_id' => $this->unit_id,
            'property' => $this->whenLoaded('property', fn () => $this->property === null ? null : [
                'id' => $this->property->id,
                'slug' => $this->property->slug,
                'title' => $this->property->title,
            ]),
            'unit' => $this->whenLoaded('unit', fn () => $this->unit === null ? null : [
                'id' => $this->unit->id,
                'property_id' => $this->unit->property_id,
                'slug' => $this->unit->slug,
                'name' => $this->unit->name,
                'occupancy_status' => $this->unit->occupancyStatus($this->organizationToday()->timezoneName)->value,
            ]),
            'tenant_contact_id' => $visiblePrimaryTenant?->contact_id,
            'tenant' => $visiblePrimaryTenant === null ? null : [
                'id' => $visiblePrimaryTenant->contact_id,
                'name' => $visiblePrimaryTenant->name_snapshot,
                'email' => $visiblePrimaryTenant->email_snapshot,
                'phone' => $visiblePrimaryTenant->contact?->phone,
            ],
            'primary_tenant' => $visiblePrimaryTenant === null ? null : [
                'id' => $visiblePrimaryTenant->contact_id,
                'name' => $visiblePrimaryTenant->name_snapshot,
                'email' => $visiblePrimaryTenant->email_snapshot,
                'phone' => $visiblePrimaryTenant->contact?->phone,
            ],
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'rent_amount' => $rent?->amount,
            'currency' => $this->currency,
            'rent_frequency' => $rent?->frequency?->value,
            'payment_due_day' => $rent?->due_day,
            'security_deposit' => $deposit?->amount,
            'guarantee_type' => $this->guarantee_type?->value ?? $this->guarantee_type,
            'activated_at' => $this->activated_at,
            'activated_by' => $this->when($canManage, $this->activated_by),
            'terminated_on' => $this->terminated_on?->toDateString(),
            'termination_reason' => $this->when($canManage, $this->termination_reason),
            'renewed_from_id' => $this->renewed_from_id,
            'notes' => $this->when($canManage, $this->notes),
            'parties' => LeasePartyResource::collection($visibleParties),
            'financial_terms' => $this->when(
                $this->relationLoaded('financialTerms'),
                fn () => $canManage
                    ? LeaseFinancialTermResource::collection($visibleTerms)
                    : $visibleTerms->map(fn (LeaseFinancialTerm $term): array => $this->tenantFinancialTermPayload($term))->values()
            ),
            'amendments' => $this->when(
                $canManage && $this->relationLoaded('amendments'),
                fn () => LeaseAmendmentResource::collection($this->amendments)
            ),
            'days_until_expiry' => $this->ends_on === null
                ? null
                : (int) $today->diffInDays($this->ends_on, false),
            'documents_count' => $this->whenCounted('documents'),
            'warnings' => $this->warnings(),
            'capabilities' => [
                'can_update' => $canManage && $this->workflow_status === LeaseWorkflowStatus::DRAFT,
                'can_activate' => $canManage && $this->workflow_status === LeaseWorkflowStatus::DRAFT,
                'can_terminate' => $canManage && $this->workflow_status === LeaseWorkflowStatus::ACTIVE && ! $isUpcoming,
                'can_cancel' => $canManage && ($this->workflow_status === LeaseWorkflowStatus::DRAFT
                    || ($this->workflow_status === LeaseWorkflowStatus::ACTIVE && $isUpcoming)),
                'can_renew' => $canManage
                    && in_array($this->workflow_status, [LeaseWorkflowStatus::ACTIVE, LeaseWorkflowStatus::TERMINATED], true)
                    && (! $this->relationLoaded('renewals') || $this->renewals->where('workflow_status', '!=', LeaseWorkflowStatus::CANCELLED)->isEmpty()),
                'can_amend' => $canManage && $this->workflow_status === LeaseWorkflowStatus::ACTIVE,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /** @return array<string, mixed> */
    private function tenantFinancialTermPayload(LeaseFinancialTerm $term): array
    {
        return [
            'id' => $term->id,
            'type' => $term->type?->value ?? $term->type,
            'calculation' => $term->calculation?->value ?? $term->calculation,
            'amount' => $term->amount,
            'percentage' => $term->percentage,
            'currency' => $term->currency,
            'frequency' => $term->frequency?->value ?? $term->frequency,
            'calculation_basis' => null,
            'due_day' => $term->due_day,
            'effective_from' => $term->effective_from?->toDateString(),
            'effective_to' => $term->effective_to?->toDateString(),
            'is_liability' => $term->is_liability,
        ];
    }

    /** @return Collection<int, LeaseFinancialTerm> */
    private function presentedFinancialTerms(): Collection
    {
        $effectiveOn = $this->starts_on !== null && $this->starts_on->gt($this->organizationToday())
            ? $this->starts_on
            : $this->organizationToday();

        return $this->financialTerms
            ->sortBy(function ($term) use ($effectiveOn): string {
                $isEffective = ($term->effective_from === null || $term->effective_from->lte($effectiveOn))
                    && ($term->effective_to === null || $term->effective_to->gte($effectiveOn));

                return sprintf(
                    '%d-%s-%010d',
                    $isEffective ? 0 : 1,
                    $term->effective_from?->format('Ymd') ?? '00000000',
                    PHP_INT_MAX - $term->id,
                );
            })
            ->values();
    }

    private function organizationToday(): Carbon
    {
        $timezone = $this->relationLoaded('organization')
            ? ($this->organization?->timezone ?? config('app.timezone'))
            : config('app.timezone');

        return Carbon::now($timezone)->startOfDay();
    }

    /** @return array<int, string> */
    private function warnings(): array
    {
        $warnings = [];

        if ($this->guarantee_type === RentalGuaranteeType::RENTAL_GUARANTEE_INSURANCE) {
            $warnings[] = 'insurance_policy_coverage_must_match_lease_period';
        }

        return $warnings;
    }
}
