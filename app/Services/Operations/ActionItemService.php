<?php

namespace App\Services\Operations;

use App\Enums\ActionItemStatus;
use App\Enums\ActionItemType;
use App\Enums\ContactAssignmentRole;
use App\Enums\DocumentLifecycle;
use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentType;
use App\Enums\LeaseWorkflowStatus;
use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Models\ActionItem;
use App\Models\ContactAssignment;
use App\Models\Document;
use App\Models\DocumentShare;
use App\Models\Lease;
use App\Models\LeaseParty;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\OperationsDigestNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ActionItemService
{
    /** @return Collection<int, ActionItem> */
    public function generateForOrganization(Organization $organization, CarbonImmutable $today): Collection
    {
        $activeKeys = [];
        $settings = $organization->settings ?? [];
        $alertDays = collect(data_get($settings, 'operations.expiry_alert_days', [30, 7]))
            ->map(fn (mixed $days): int => max(1, (int) $days))
            ->unique()
            ->sort()
            ->values();
        $maximumAlertDays = $alertDays->max() ?? 30;

        Lease::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('workflow_status', LeaseWorkflowStatus::ACTIVE->value)
            ->whereBetween('ends_on', [$today->toDateString(), $today->addDays($maximumAlertDays)->toDateString()])
            ->with(['property:id,title', 'unit:id,name'])
            ->orderBy('id')
            ->each(function (Lease $lease) use ($organization, $today, $alertDays, &$activeKeys): void {
                $daysRemaining = $today->diffInDays($lease->ends_on, false);
                $window = $alertDays->first(fn (int $days): bool => $daysRemaining <= $days) ?? $alertDays->last();
                $key = "lease-expiry-{$lease->id}";
                $activeKeys[] = $key;

                $this->upsert($organization, $key, [
                    'lease_id' => $lease->id,
                    'property_id' => $lease->property_id,
                    'unit_id' => $lease->unit_id,
                    'type' => ActionItemType::LEASE_EXPIRY,
                    'priority' => $daysRemaining <= 7 ? 'high' : 'normal',
                    'title' => $lease->title ?: "Lease #{$lease->id} expires soon",
                    'description' => "{$daysRemaining} day(s) remain in the current lease term.",
                    'due_on' => $lease->ends_on,
                    'metadata' => ['generated' => true, 'alert_window_days' => $window],
                ]);
            });

        Document::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNotIn('lifecycle', [DocumentLifecycle::ARCHIVED->value, DocumentLifecycle::SUPERSEDED->value])
            ->whereBetween('expires_on', [$today->toDateString(), $today->addDays($maximumAlertDays)->toDateString()])
            ->orderBy('id')
            ->each(function (Document $document) use ($organization, $today, $alertDays, &$activeKeys): void {
                $daysRemaining = $today->diffInDays($document->expires_on, false);
                $window = $alertDays->first(fn (int $days): bool => $daysRemaining <= $days) ?? $alertDays->last();
                $key = "document-expiry-{$document->id}";
                $activeKeys[] = $key;

                $this->upsert($organization, $key, [
                    'document_id' => $document->id,
                    'property_id' => $document->property_id,
                    'unit_id' => $document->unit_id,
                    'type' => ActionItemType::DOCUMENT_EXPIRY,
                    'priority' => $daysRemaining <= 7 ? 'high' : 'normal',
                    'title' => "{$document->title} expires soon",
                    'description' => "{$daysRemaining} day(s) remain before expiry.",
                    'due_on' => $document->expires_on,
                    'metadata' => ['generated' => true, 'alert_window_days' => $window],
                ]);
            });

        Document::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNotIn('lifecycle', [DocumentLifecycle::ARCHIVED->value, DocumentLifecycle::SUPERSEDED->value])
            ->whereDate('expires_on', '<', $today->toDateString())
            ->whereIn('type', [DocumentType::INSURANCE_POLICY->value, DocumentType::COMPLIANCE_CERTIFICATE->value])
            ->orderBy('id')
            ->each(function (Document $document) use ($organization, &$activeKeys): void {
                $isInsurance = $document->type === DocumentType::INSURANCE_POLICY;
                $type = $isInsurance ? ActionItemType::EXPIRED_INSURANCE : ActionItemType::EXPIRED_COMPLIANCE;
                $key = "{$type->value}-{$document->id}";
                $activeKeys[] = $key;

                $this->upsert($organization, $key, [
                    'document_id' => $document->id,
                    'property_id' => $document->property_id,
                    'unit_id' => $document->unit_id,
                    'type' => $type,
                    'priority' => 'high',
                    'title' => "{$document->title} has expired",
                    'description' => 'Replace or renew this record to restore valid coverage or compliance.',
                    'due_on' => $document->expires_on,
                    'metadata' => ['generated' => true],
                ]);
            });

        Document::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNotIn('lifecycle', [DocumentLifecycle::ARCHIVED->value, DocumentLifecycle::SUPERSEDED->value])
            ->whereHas('requiredSigners', fn ($query) => $query->where('status', DocumentSignerStatus::PENDING->value))
            ->withCount(['requiredSigners as pending_signers_count' => fn ($query) => $query->where('status', DocumentSignerStatus::PENDING->value)])
            ->orderBy('id')
            ->each(function (Document $document) use ($organization, &$activeKeys): void {
                $key = "pending-signature-{$document->id}";
                $activeKeys[] = $key;

                $this->upsert($organization, $key, [
                    'document_id' => $document->id,
                    'property_id' => $document->property_id,
                    'unit_id' => $document->unit_id,
                    'type' => ActionItemType::PENDING_SIGNATURE,
                    'priority' => 'normal',
                    'title' => "Signatures pending for {$document->title}",
                    'description' => "{$document->pending_signers_count} required signer(s) still need action.",
                    'due_on' => $document->expires_on,
                    'metadata' => ['generated' => true, 'pending_signers' => $document->pending_signers_count],
                ]);
            });

        Lease::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('workflow_status', LeaseWorkflowStatus::ACTIVE->value)
            ->whereDate('starts_on', '<=', $today->toDateString())
            ->where(function ($query) use ($today): void {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today->toDateString());
            })
            ->whereDoesntHave('documents', function ($query): void {
                $query->withoutGlobalScopes()
                    ->where('type', DocumentType::INSPECTION_REPORT->value)
                    ->where('metadata->inspection_type', 'move_in');
            })
            ->orderBy('id')
            ->each(function (Lease $lease) use ($organization, &$activeKeys): void {
                $key = "missing-move-in-inspection-{$lease->id}";
                $activeKeys[] = $key;

                $this->upsert($organization, $key, [
                    'lease_id' => $lease->id,
                    'property_id' => $lease->property_id,
                    'unit_id' => $lease->unit_id,
                    'type' => ActionItemType::MISSING_MOVE_IN_INSPECTION,
                    'priority' => 'high',
                    'title' => ($lease->title ?: "Lease #{$lease->id}") . ' needs a move-in inspection',
                    'description' => 'Attach a move-in inspection report with condition evidence.',
                    'due_on' => $lease->starts_on,
                    'metadata' => ['generated' => true],
                ]);
            });

        ActionItem::query()
            ->where('organization_id', $organization->id)
            ->where('status', ActionItemStatus::OPEN->value)
            ->where('metadata->generated', true)
            ->whereNotIn('unique_key', $activeKeys ?: ['__none__'])
            ->update([
                'status' => ActionItemStatus::COMPLETED->value,
                'resolved_at' => now(),
            ]);

        return ActionItem::query()
            ->where('organization_id', $organization->id)
            ->where('status', ActionItemStatus::OPEN->value)
            ->with(['lease', 'document', 'property', 'unit'])
            ->orderByRaw('CASE WHEN priority = ? THEN 0 ELSE 1 END', ['high'])
            ->orderBy('due_on')
            ->orderBy('id')
            ->get();
    }

    /** @param Collection<int, ActionItem> $actionItems */
    public function sendDailyDigests(Organization $organization, CarbonImmutable $today, Collection $actionItems): int
    {
        $sent = 0;
        $organization->users()
            ->wherePivot('status', OrganizationMemberStatus::ACTIVE->value)
            ->wherePivotIn('role', [
                ...OrganizationMemberRole::operationalRoles(),
                OrganizationMemberRole::TENANT->value,
            ])
            ->orderBy('users.id')
            ->get()
            ->each(function (User $user) use ($organization, $today, $actionItems, &$sent): void {
                $visibleItems = $this->visibleItemsForUser($user, $organization, $actionItems);

                if ($visibleItems->isEmpty()) {
                    return;
                }

                $inserted = DB::table('operations_digest_deliveries')->insertOrIgnore([
                    'organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'digest_on' => $today->toDateString(),
                    'sent_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($inserted === 0) {
                    return;
                }

                $user->notify(
                    (new OperationsDigestNotification($organization, $visibleItems))->afterCommit()
                );
                $sent++;
            });

        return $sent;
    }

    /** @param array<string, mixed> $attributes */
    private function upsert(Organization $organization, string $key, array $attributes): ActionItem
    {
        $item = ActionItem::query()->firstOrNew([
            'organization_id' => $organization->id,
            'unique_key' => $key,
        ]);

        if (! $item->exists) {
            $item->status = ActionItemStatus::OPEN;
        }

        $item->fill($attributes)->save();

        return $item;
    }

    /**
     * @param  Collection<int, ActionItem> $actionItems
     * @return Collection<int, ActionItem>
     */
    private function visibleItemsForUser(User $user, Organization $organization, Collection $actionItems): Collection
    {
        $role = $user->membershipRole($organization->id);

        if ($role === OrganizationMemberRole::AGENT) {
            $today = CarbonImmutable::now($organization->timezone ?: config('app.timezone'))->toDateString();
            $assignments = ContactAssignment::query()
                ->where('organization_id', $organization->id)
                ->whereIn('role', [ContactAssignmentRole::AGENT->value, ContactAssignmentRole::BROKER->value])
                ->whereHas('contact', fn ($query) => $query->where('user_id', $user->id))
                ->where(function ($query) use ($today): void {
                    $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', $today);
                })
                ->where(function ($query) use ($today): void {
                    $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today);
                })
                ->get(['property_id', 'unit_id']);
            $propertyIds = $assignments->pluck('property_id')->unique();
            $unitIds = $assignments->pluck('unit_id')->filter()->unique();

            return $actionItems
                ->filter(fn (ActionItem $item): bool => ($item->property_id !== null && $propertyIds->contains($item->property_id))
                    || ($item->unit_id !== null && $unitIds->contains($item->unit_id)))
                ->values();
        }

        if ($role !== OrganizationMemberRole::TENANT) {
            return $actionItems;
        }

        $leaseIds = LeaseParty::query()
            ->where('organization_id', $organization->id)
            ->whereHas('contact', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('lease_id');
        $documentIds = DocumentShare::query()
            ->active()
            ->where('user_id', $user->id)
            ->pluck('document_id');

        return $actionItems
            ->filter(fn (ActionItem $item): bool => ($item->lease_id !== null && $leaseIds->contains($item->lease_id))
                || ($item->document_id !== null && $documentIds->contains($item->document_id)))
            ->values();
    }
}
