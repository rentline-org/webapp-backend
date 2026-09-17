<?php

namespace App\Services\Organization;

use App\Enums\ContactAssignmentRole;
use App\Enums\OrganizationMemberRole;
use App\Models\Contact;
use App\Models\ContactAssignment;
use App\Models\Document;
use App\Models\Lease;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AssignedPropertyAccess
{
    /** @var array<string, array{broad_property_ids: list<int>, property_ids: list<int>, unit_ids: list<int>}> */
    private array $scopes = [];

    public function isRestrictedAgent(User $user, int $organizationId): bool
    {
        return ! $user->isSuperAdmin()
            && $user->membershipRole($organizationId) === OrganizationMemberRole::AGENT;
    }

    /** @return array{broad_property_ids: list<int>, property_ids: list<int>, unit_ids: list<int>} */
    public function scope(User $user, int $organizationId): array
    {
        $key = $user->id . ':' . $organizationId;
        if (isset($this->scopes[$key])) {
            return $this->scopes[$key];
        }

        $timezone = Organization::query()->whereKey($organizationId)->value('timezone')
            ?? config('app.timezone');
        $today = Carbon::now($timezone)->toDateString();
        $assignments = ContactAssignment::query()
            ->where('organization_id', $organizationId)
            ->whereIn('role', [ContactAssignmentRole::AGENT->value, ContactAssignmentRole::BROKER->value])
            ->whereHas('contact', fn (Builder $query) => $query->where('user_id', $user->id))
            ->where(fn (Builder $query) => $query
                ->whereNull('starts_on')
                ->orWhereDate('starts_on', '<=', $today))
            ->where(fn (Builder $query) => $query
                ->whereNull('ends_on')
                ->orWhereDate('ends_on', '>=', $today))
            ->get(['property_id', 'unit_id']);

        $unitIds = $assignments->pluck('unit_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values();
        $broadPropertyIds = $assignments
            ->whereNull('unit_id')
            ->pluck('property_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $unitPropertyIds = Unit::query()
            ->whereIn('id', $unitIds)
            ->pluck('property_id')
            ->map(fn ($id): int => (int) $id);

        return $this->scopes[$key] = [
            'broad_property_ids' => $broadPropertyIds->all(),
            'property_ids' => $broadPropertyIds->concat($unitPropertyIds)->unique()->values()->all(),
            'unit_ids' => $unitIds->all(),
        ];
    }

    public function canAccessProperty(User $user, int $organizationId, int $propertyId): bool
    {
        if (! $this->isRestrictedAgent($user, $organizationId)) {
            return true;
        }

        return in_array($propertyId, $this->scope($user, $organizationId)['property_ids'], true);
    }

    public function canManageProperty(User $user, int $organizationId, int $propertyId): bool
    {
        if (! $this->isRestrictedAgent($user, $organizationId)) {
            return true;
        }

        return in_array($propertyId, $this->scope($user, $organizationId)['broad_property_ids'], true);
    }

    public function canAccessUnit(User $user, int $organizationId, int $unitId): bool
    {
        if (! $this->isRestrictedAgent($user, $organizationId)) {
            return true;
        }

        $scope = $this->scope($user, $organizationId);
        if (in_array($unitId, $scope['unit_ids'], true)) {
            return true;
        }

        return Unit::query()
            ->whereKey($unitId)
            ->whereIn('property_id', $scope['broad_property_ids'])
            ->exists();
    }

    public function canAccessLease(User $user, Lease $lease): bool
    {
        return $this->canAccessContext(
            $user,
            $lease->organization_id,
            $lease->property_id,
            $lease->unit_id,
        );
    }

    public function canAccessDocument(User $user, Document $document): bool
    {
        if (! $this->isRestrictedAgent($user, $document->organization_id)) {
            return true;
        }

        $scope = $this->scope($user, $document->organization_id);

        return ($document->property_id !== null && in_array($document->property_id, $scope['broad_property_ids'], true))
            || ($document->unit_id !== null && $this->canAccessUnit($user, $document->organization_id, $document->unit_id))
            || $document->properties()->whereIn('properties.id', $scope['broad_property_ids'])->exists()
            || $document->units()->where(function (Builder $query) use ($scope): void {
                $query->whereIn('units.id', $scope['unit_ids'])
                    ->orWhereIn('units.property_id', $scope['broad_property_ids']);
            })->exists()
            || $document->leases()->where(function (Builder $query) use ($scope): void {
                $query->whereIn('leases.property_id', $scope['broad_property_ids'])
                    ->orWhereIn('leases.unit_id', $scope['unit_ids']);
            })->exists();
    }

    public function canAccessContact(User $user, Contact $contact): bool
    {
        if (! $this->isRestrictedAgent($user, $contact->organization_id)) {
            return true;
        }

        $scope = $this->scope($user, $contact->organization_id);

        return Contact::query()
            ->whereKey($contact->id)
            ->where(function (Builder $query) use ($scope): void {
                $query->whereHas('assignments', fn (Builder $query) => $query
                    ->whereIn('property_id', $scope['broad_property_ids'])
                    ->orWhereIn('unit_id', $scope['unit_ids']))
                    ->orWhereHas('properties', fn (Builder $query) => $query->whereIn('properties.id', $scope['broad_property_ids']))
                    ->orWhereHas('leaseParties.lease', fn (Builder $query) => $query
                        ->whereIn('property_id', $scope['broad_property_ids'])
                        ->orWhereIn('unit_id', $scope['unit_ids']))
                    ->orWhereHas('documentParties.document', fn (Builder $query) => $query
                        ->whereIn('property_id', $scope['broad_property_ids'])
                        ->orWhereIn('unit_id', $scope['unit_ids']));
            })
            ->exists();
    }

    public function restrictProperties(Builder $query, User $user, int $organizationId): Builder
    {
        if ($this->isRestrictedAgent($user, $organizationId)) {
            $query->whereIn('properties.id', $this->scope($user, $organizationId)['property_ids']);
        }

        return $query;
    }

    public function restrictLeases(Builder $query, User $user, int $organizationId): Builder
    {
        if (! $this->isRestrictedAgent($user, $organizationId)) {
            return $query;
        }

        $scope = $this->scope($user, $organizationId);

        return $query->where(fn (Builder $query) => $query
            ->whereIn('property_id', $scope['broad_property_ids'])
            ->orWhereIn('unit_id', $scope['unit_ids']));
    }

    public function restrictDocuments(Builder $query, User $user, int $organizationId): Builder
    {
        if (! $this->isRestrictedAgent($user, $organizationId)) {
            return $query;
        }

        $scope = $this->scope($user, $organizationId);

        return $query->where(function (Builder $query) use ($scope): void {
            $query->whereIn('property_id', $scope['broad_property_ids'])
                ->orWhereIn('unit_id', $scope['unit_ids'])
                ->orWhereHas('properties', fn (Builder $query) => $query->whereIn('properties.id', $scope['broad_property_ids']))
                ->orWhereHas('units', fn (Builder $query) => $query
                    ->whereIn('units.id', $scope['unit_ids'])
                    ->orWhereIn('units.property_id', $scope['broad_property_ids']))
                ->orWhereHas('leases', fn (Builder $query) => $query
                    ->whereIn('leases.property_id', $scope['broad_property_ids'])
                    ->orWhereIn('leases.unit_id', $scope['unit_ids']));
        });
    }

    private function canAccessContext(
        User $user,
        int $organizationId,
        ?int $propertyId,
        ?int $unitId,
    ): bool {
        if (! $this->isRestrictedAgent($user, $organizationId)) {
            return true;
        }

        if ($unitId !== null && $this->canAccessUnit($user, $organizationId, $unitId)) {
            return true;
        }

        return $propertyId !== null
            && in_array($propertyId, $this->scope($user, $organizationId)['broad_property_ids'], true);
    }
}
