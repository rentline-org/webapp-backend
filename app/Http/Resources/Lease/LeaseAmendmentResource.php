<?php

namespace App\Http\Resources\Lease;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaseAmendmentResource extends JsonResource
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
            'status' => $this->status,
            'effective_on' => $this->effective_on?->toDateString(),
            'summary' => $this->summary,
            'activated_at' => $this->activated_at,
            'created_by' => $this->created_by,
            'activated_by' => $this->activated_by,
            'financial_terms' => LeaseFinancialTermResource::collection($this->whenLoaded('terms')),
            'created_at' => $this->created_at,
        ];
    }
}
