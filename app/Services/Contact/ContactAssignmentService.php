<?php

namespace App\Services\Contact;

use App\Enums\ContactAssignmentSource;
use App\Models\Contact;
use App\Models\ContactAssignment;
use App\Models\Property;
use App\Models\Unit;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ContactAssignmentService
{
    public function __construct(private readonly ActiveOrganizationContext $activeOrganizationContext) {}

    /** @param array<string, mixed> $attributes */
    public function create(Contact $contact, array $attributes): ContactAssignment
    {
        return DB::transaction(function () use ($contact, $attributes): ContactAssignment {
            $organizationId = $this->organizationId();
            $this->ensureContactOrganization($contact, $organizationId);
            $this->validateContext($attributes, $organizationId);

            return $contact->assignments()->create([
                ...$attributes,
                'organization_id' => $organizationId,
                'source' => ContactAssignmentSource::MANUAL->value,
                'lease_id' => null,
            ])->load(['property', 'unit']);
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
            $assignment->update($attributes);

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
        $property = Property::query()->where('organization_id', $organizationId)->find($attributes['property_id']);

        if ($property === null) {
            throw ValidationException::withMessages(['property_id' => ['The selected property is invalid.']]);
        }

        if (($attributes['unit_id'] ?? null) === null) {
            return;
        }

        if (! Unit::query()->whereKey($attributes['unit_id'])->whereBelongsTo($property)->exists()) {
            throw ValidationException::withMessages(['unit_id' => ['The selected unit must belong to the selected property.']]);
        }
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
}
