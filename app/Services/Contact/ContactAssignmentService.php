<?php

namespace App\Services\Contact;

use App\Enums\ContactAssignmentRole;
use App\Enums\ContactAssignmentSource;
use App\Models\Contact;
use App\Models\ContactAssignment;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Organization\ActiveOrganizationContext;
use App\Services\Organization\AssignedPropertyAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ContactAssignmentService
{
    public function __construct(
        private readonly ActiveOrganizationContext $activeOrganizationContext,
        private readonly AssignedPropertyAccess $assignedPropertyAccess,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(Contact $contact, array $attributes): ContactAssignment
    {
        return DB::transaction(function () use ($contact, $attributes): ContactAssignment {
            $organizationId = $this->organizationId();
            $this->ensureContactOrganization($contact, $organizationId);
            $this->validateContext($attributes, $organizationId);
            $this->validateAssignmentValues($attributes);

            $assignment = $contact->assignments()->create([
                ...$attributes,
                'organization_id' => $organizationId,
                'source' => ContactAssignmentSource::MANUAL->value,
                'lease_id' => null,
            ]);
            $this->enforcePrimaryAssignment($assignment);

            return $assignment->load(['property', 'unit']);
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Contact $contact, ContactAssignment $assignment, array $attributes): ContactAssignment
    {
        return DB::transaction(function () use ($contact, $assignment, $attributes): ContactAssignment {
            $organizationId = $this->organizationId();
            $this->ensureAssignmentContext($contact, $assignment, $organizationId);

            if ($assignment->source !== ContactAssignmentSource::MANUAL) {
                throw new ConflictHttpException('Lease-derived assignments can only be changed through the lease workflow.');
            }

            $context = [
                'property_id' => $attributes['property_id'] ?? $assignment->property_id,
                'unit_id' => array_key_exists('unit_id', $attributes) ? $attributes['unit_id'] : $assignment->unit_id,
            ];
            $this->validateContext($context, $organizationId);
            $this->validateAssignmentValues([
                ...$assignment->only(['role', 'starts_on', 'ends_on', 'ownership_percentage']),
                ...$attributes,
            ]);
            $assignment->update($attributes);
            $this->enforcePrimaryAssignment($assignment);

            return $assignment->refresh()->load(['property', 'unit']);
        });
    }

    public function delete(Contact $contact, ContactAssignment $assignment): void
    {
        $this->ensureAssignmentContext($contact, $assignment, $this->organizationId());

        if ($assignment->source !== ContactAssignmentSource::MANUAL) {
            throw new ConflictHttpException('Lease-derived assignments can only be changed through the lease workflow.');
        }

        $assignment->delete();
    }

    /** @param array<string, mixed> $attributes */
    private function validateContext(array $attributes, int $organizationId): void
    {
        $property = Property::query()
            ->where('organization_id', $organizationId)
            ->whereNull('archived_at')
            ->find($attributes['property_id']);

        if ($property === null) {
            throw ValidationException::withMessages(['property_id' => ['The selected property is invalid.']]);
        }

        if (($attributes['unit_id'] ?? null) === null) {
            $this->ensureAgentCanUseProperty($organizationId, $property->id);

            return;
        }

        $unit = Unit::query()
            ->whereKey($attributes['unit_id'])
            ->whereBelongsTo($property)
            ->whereNull('archived_at')
            ->first();

        if ($unit === null) {
            throw ValidationException::withMessages(['unit_id' => ['The selected unit must belong to the selected property.']]);
        }

        $user = auth()->user();
        if ($user !== null
            && ! $this->assignedPropertyAccess->canAccessUnit($user, $organizationId, $unit->id)) {
            throw new AuthorizationException('The selected unit is not assigned to this agent.');
        }
    }

    /** @param array<string, mixed> $attributes */
    private function validateAssignmentValues(array $attributes): void
    {
        $errors = [];
        $role = $attributes['role'] instanceof ContactAssignmentRole
            ? $attributes['role']
            : ContactAssignmentRole::from((string) $attributes['role']);

        if (($attributes['ownership_percentage'] ?? null) !== null && $role !== ContactAssignmentRole::OWNER) {
            $errors['ownership_percentage'][] = 'Ownership percentage is only valid for owner assignments.';
        }

        if (($attributes['starts_on'] ?? null) !== null
            && ($attributes['ends_on'] ?? null) !== null
            && Carbon::parse($attributes['ends_on'])->lt(Carbon::parse($attributes['starts_on']))) {
            $errors['ends_on'][] = 'The assignment end date cannot be before its start date.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function enforcePrimaryAssignment(ContactAssignment $assignment): void
    {
        if (! $assignment->is_primary) {
            return;
        }

        ContactAssignment::query()
            ->where('organization_id', $assignment->organization_id)
            ->where('property_id', $assignment->property_id)
            ->where('role', $assignment->role->value)
            ->when(
                $assignment->unit_id === null,
                fn ($query) => $query->whereNull('unit_id'),
                fn ($query) => $query->where('unit_id', $assignment->unit_id),
            )
            ->whereKeyNot($assignment->id)
            ->update(['is_primary' => false]);
    }

    private function organizationId(): int
    {
        return $this->activeOrganizationContext->id()
            ?? throw new AuthorizationException('An active organization is required.');
    }

    private function ensureContactOrganization(Contact $contact, int $organizationId): void
    {
        if ($contact->organization_id !== $organizationId) {
            throw new AuthorizationException;
        }
    }

    private function ensureAssignmentContext(Contact $contact, ContactAssignment $assignment, int $organizationId): void
    {
        $this->ensureContactOrganization($contact, $organizationId);

        if ($assignment->contact_id !== $contact->id || $assignment->organization_id !== $organizationId) {
            abort(404);
        }
    }

    private function ensureAgentCanUseProperty(int $organizationId, int $propertyId): void
    {
        $user = auth()->user();
        if ($user !== null
            && ! $this->assignedPropertyAccess->canManageProperty($user, $organizationId, $propertyId)) {
            throw new AuthorizationException('The selected property is not assigned to this agent.');
        }
    }
}
