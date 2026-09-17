<?php

namespace App\Http\Controllers\Api\V1\ActionItem;

use App\Enums\ActionItemStatus;
use App\Enums\ActionItemType;
use App\Enums\ContactAssignmentRole;
use App\Enums\OrganizationMemberRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActionItem\ActionItemResource;
use App\Models\ActionItem;
use App\Models\ContactAssignment;
use App\Models\Organization;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ActionItemController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = app(ActiveOrganizationContext::class)->id();
        abort_unless($organizationId !== null, Response::HTTP_FORBIDDEN);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(ActionItemStatus::class)],
            'type' => ['sometimes', Rule::enum(ActionItemType::class)],
            'property_id' => ['sometimes', 'integer', 'min:1'],
            'unit_id' => ['sometimes', 'integer', 'min:1'],
            'lease_id' => ['sometimes', 'integer', 'min:1'],
            'document_id' => ['sometimes', 'integer', 'min:1'],
            'due_before' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $query = $this->visibleQuery($request, $organizationId);

        foreach (['status', 'type', 'property_id', 'unit_id', 'lease_id', 'document_id'] as $filter) {
            if (array_key_exists($filter, $validated)) {
                $query->where($filter, $validated[$filter]);
            }
        }

        if (isset($validated['due_before'])) {
            $query->whereDate('due_on', '<=', $validated['due_before']);
        }

        return ActionItemResource::collection(
            $query
                ->orderByRaw('CASE WHEN priority = ? THEN 0 ELSE 1 END', ['high'])
                ->orderBy('due_on')
                ->orderBy('id')
                ->paginate((int) ($validated['per_page'] ?? 20))
                ->withQueryString()
        );
    }

    public function update(Request $request, int $actionItem): ActionItemResource
    {
        $organizationId = app(ActiveOrganizationContext::class)->id();
        abort_unless($request->user()->canOperateActiveOrganization($organizationId), Response::HTTP_FORBIDDEN);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ActionItemStatus::class)],
        ]);
        $item = $this->visibleQuery($request, $organizationId)->findOrFail($actionItem);
        $status = ActionItemStatus::from($data['status']);

        $item->update([
            'status' => $status,
            'resolved_at' => $status === ActionItemStatus::OPEN ? null : now(),
        ]);

        return new ActionItemResource($item->refresh());
    }

    /** @return Builder<ActionItem> */
    private function visibleQuery(Request $request, int $organizationId): Builder
    {
        $user = $request->user();
        $query = ActionItem::query()
            ->where('organization_id', $organizationId)
            ->with(['lease', 'document', 'property', 'unit']);
        $role = $user->membershipRole($organizationId);

        if ($role === OrganizationMemberRole::AGENT) {
            $timezone = Organization::query()->whereKey($organizationId)->value('timezone')
                ?? config('app.timezone');
            $today = Carbon::now($timezone)->toDateString();
            $assignments = ContactAssignment::query()
                ->where('organization_id', $organizationId)
                ->whereIn('role', [ContactAssignmentRole::AGENT->value, ContactAssignmentRole::BROKER->value])
                ->whereHas('contact', fn ($contactQuery) => $contactQuery->where('user_id', $user->id))
                ->where(fn ($assignmentQuery) => $assignmentQuery
                    ->whereNull('starts_on')
                    ->orWhereDate('starts_on', '<=', $today))
                ->where(fn ($assignmentQuery) => $assignmentQuery
                    ->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $today))
                ->get(['property_id', 'unit_id']);
            $propertyIds = $assignments->pluck('property_id')->filter()->unique();
            $unitIds = $assignments->pluck('unit_id')->filter()->unique();

            $query->where(fn ($itemQuery) => $itemQuery
                ->whereIn('property_id', $propertyIds)
                ->orWhereIn('unit_id', $unitIds));
        } elseif (! $user->canOperateActiveOrganization($organizationId)) {
            abort_unless($role === OrganizationMemberRole::TENANT, Response::HTTP_FORBIDDEN);

            $query->where(function ($tenantQuery) use ($user): void {
                $tenantQuery
                    ->whereHas('lease.parties.contact', fn ($contactQuery) => $contactQuery->where('user_id', $user->id))
                    ->orWhereHas('document.shares', fn ($shareQuery) => $shareQuery
                        ->where('user_id', $user->id)
                        ->whereNull('revoked_at'));
            });
        }

        return $query;
    }
}
