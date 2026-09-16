<?php

namespace App\Http\Resources\Lease;

use App\Enums\RentalGuaranteeType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationalLeaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'title' => $this->title,
            'workflow_status' => $this->workflow_status?->value ?? $this->workflow_status,
            'temporal_status' => $this->status()->value,
            'property_id' => $this->property_id,
            'unit_id' => $this->unit_id,
            'property' => $this->whenLoaded('property', fn () => $this->property === null ? null : [
                'id' => $this->property->id,
                'slug' => $this->property->slug,
                'title' => $this->property->title,
            ]),
            'unit' => $this->whenLoaded('unit', fn () => $this->unit === null ? null : [
                'id' => $this->unit->id,
                'slug' => $this->unit->slug,
                'name' => $this->unit->name,
                'occupancy_status' => $this->unit->occupancyStatus()->value,
            ]),
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'currency' => $this->currency,
            'guarantee_type' => $this->guarantee_type?->value ?? $this->guarantee_type,
            'activated_at' => $this->activated_at,
            'activated_by' => $this->activated_by,
            'terminated_on' => $this->terminated_on?->toDateString(),
            'termination_reason' => $this->termination_reason,
            'renewed_from_id' => $this->renewed_from_id,
            'notes' => $this->notes,
            'parties' => LeasePartyResource::collection($this->whenLoaded('parties')),
            'financial_terms' => LeaseFinancialTermResource::collection($this->whenLoaded('financialTerms')),
            'amendments' => LeaseAmendmentResource::collection($this->whenLoaded('amendments')),
            'warnings' => $this->warnings(),
            'capabilities' => [
                'can_update' => $request->user()?->can('update', $this->resource) ?? false,
                'can_activate' => $request->user()?->can('update', $this->resource) ?? false,
                'can_terminate' => $request->user()?->can('update', $this->resource) ?? false,
                'can_renew' => $request->user()?->can('update', $this->resource) ?? false,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
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
