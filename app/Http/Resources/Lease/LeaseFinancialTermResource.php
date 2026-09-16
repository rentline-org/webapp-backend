<?php

namespace App\Http\Resources\Lease;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaseFinancialTermResource extends JsonResource
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
            'lease_amendment_id' => $this->lease_amendment_id,
            'type' => $this->type?->value ?? $this->type,
            'calculation' => $this->calculation?->value ?? $this->calculation,
            'amount' => $this->amount,
            'percentage' => $this->percentage,
            'currency' => $this->currency,
            'frequency' => $this->frequency?->value ?? $this->frequency,
            'calculation_basis' => $this->calculation_basis,
            'due_day' => $this->due_day,
            'payer_contact_id' => $this->payer_contact_id,
            'payee_contact_id' => $this->payee_contact_id,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_liability' => $this->is_liability,
        ];
    }
}
