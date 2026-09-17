<?php

namespace App\Http\Resources\ActionItem;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActionItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canManage = $request->user()?->canOperateActiveOrganization($this->organization_id) ?? false;

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'priority' => $this->priority,
            'title' => $this->title,
            'description' => $this->description,
            'due_on' => $this->due_on?->toDateString(),
            'lease_id' => $this->lease_id,
            'document_id' => $this->document_id,
            'property_id' => $this->property_id,
            'unit_id' => $this->unit_id,
            'metadata' => $this->metadata,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'capabilities' => [
                'can_complete' => $canManage && $this->status->value === 'open',
                'can_dismiss' => $canManage && $this->status->value === 'open',
                'can_reopen' => $canManage && $this->status->value !== 'open',
            ],
        ];
    }
}
