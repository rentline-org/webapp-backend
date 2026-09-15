<?php

namespace App\Http\Resources\Document;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_contact_id' => $this->tenant_contact_id,
            'tenant' => [
                'id' => $this->tenant?->id,
                'name' => $this->tenant?->name ?? $this->tenant_name_snapshot,
                'email' => $this->tenant?->email ?? $this->tenant_email_snapshot,
                'phone' => $this->tenant?->phone,
                'type' => $this->tenant?->type?->value ?? 'tenant',
            ],
            'property_title_snapshot' => $this->property_title_snapshot,
            'unit_name_snapshot' => $this->unit_name_snapshot,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'rent_amount' => (float) $this->rent_amount,
            'currency' => $this->currency,
            'security_deposit' => $this->security_deposit === null
                ? null
                : (float) $this->security_deposit,
            'notes' => $this->notes,
            'status' => $this->status()->value,
        ];
    }
}
