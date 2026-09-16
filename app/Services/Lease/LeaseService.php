<?php

namespace App\Services\Lease;

use App\Enums\ContactAssignmentRole;
use App\Enums\ContactAssignmentSource;
use App\Enums\LeaseFinancialTermCalculation;
use App\Enums\LeaseFinancialTermFrequency;
use App\Enums\LeaseFinancialTermType;
use App\Enums\LeasePartyRole;
use App\Enums\LeaseWorkflowStatus;
use App\Enums\RentalGuaranteeType;
use App\Models\Contact;
use App\Models\ContactAssignment;
use App\Models\Lease;
use App\Models\LeaseAmendment;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class LeaseService
{
    public function __construct(private readonly ActiveOrganizationContext $activeOrganizationContext) {}

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters, User $user): LengthAwarePaginator
    {
        $organizationId = $this->organizationId();
        $query = Lease::query()
            ->where('organization_id', $organizationId)
            ->with($this->relations())
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $canOperate = method_exists($user, 'canOperateActiveOrganization')
            ? $user->canOperateActiveOrganization()
            : ($user->isLandlord() || $user->isSuperAdmin());

        if (! $canOperate) {
            $query->whereHas('parties.contact', fn (Builder $query) => $query->where('user_id', $user->id));
        }

        if (! empty($filters['search'])) {
            $search = '%'.trim((string) $filters['search']).'%';
            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('title', $search)
                    ->orWhereHas('property', fn (Builder $query) => $query->whereLike('title', $search))
                    ->orWhereHas('unit', fn (Builder $query) => $query->whereLike('name', $search))
                    ->orWhereHas('parties', fn (Builder $query) => $query->whereLike('name_snapshot', $search));
            });
        }

        foreach (['workflow_status', 'property_id', 'unit_id'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        if (! empty($filters['contact_id'])) {
            $query->whereHas('parties', fn (Builder $query) => $query->where('contact_id', $filters['contact_id']));
        }

        $today = today()->toDateString();
        match ($filters['temporal_status'] ?? null) {
            'upcoming' => $query->whereDate('starts_on', '>', $today),
            'active' => $query->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today),
            'expired' => $query->whereDate('ends_on', '<', $today),
            default => null,
        };

        if (isset($filters['expiring_within_days'])) {
            $query->whereBetween('ends_on', [$today, today()->addDays((int) $filters['expiring_within_days'])->toDateString()]);
        }

        return $query->paginate(min((int) ($filters['per_page'] ?? 15), 100));
    }

    public function load(Lease $lease): Lease
    {
        $this->ensureOrganization($lease);

        return $lease->load($this->relations());
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Lease
    {
        return DB::transaction(fn (): Lease => $this->createDraft($data));
    }

    /** @param array<string, mixed> $data */
    public function update(Lease $lease, array $data): Lease
    {
        return DB::transaction(function () use ($lease, $data): Lease {
            $lease = Lease::query()->whereKey($lease->id)->lockForUpdate()->firstOrFail();
            $this->ensureOrganization($lease);

            if ($lease->workflow_status !== LeaseWorkflowStatus::DRAFT) {
                throw new ConflictHttpException('Only draft leases can be edited. Use an amendment for an active lease.');
            }

            $propertyId = (int) ($data['property_id'] ?? $lease->property_id);
            $unitId = (int) ($data['unit_id'] ?? $lease->unit_id);
            [$property, $unit] = $this->resolveContext($propertyId, $unitId);
            $dates = [
                'starts_on' => array_key_exists('starts_on', $data) ? $data['starts_on'] : $lease->starts_on?->toDateString(),
                'ends_on' => array_key_exists('ends_on', $data) ? $data['ends_on'] : $lease->ends_on?->toDateString(),
            ];
            $this->validatePeriod($dates['starts_on'], $dates['ends_on']);

            $lease->update([
                ...Arr::only($data, ['title', 'starts_on', 'ends_on', 'currency', 'guarantee_type', 'notes']),
                'property_id' => $property->id,
                'unit_id' => $unit->id,
                'property_title_snapshot' => $property->title,
                'unit_name_snapshot' => $unit->name,
            ]);

            if (array_key_exists('parties', $data)) {
                $this->syncParties($lease, $data['parties']);
            }

            if (array_key_exists('financial_terms', $data)) {
                $this->syncFinancialTerms($lease, $data['financial_terms']);
            }

            $this->syncLegacySummary($lease);

            return $this->load($lease->refresh());
        });
    }

    public function delete(Lease $lease): void
    {
        $this->ensureOrganization($lease);

        if ($lease->workflow_status !== LeaseWorkflowStatus::DRAFT) {
            throw new ConflictHttpException('Only draft leases can be deleted. Cancel or terminate this lease instead.');
        }

        $lease->delete();
    }

    public function activate(Lease $lease, User $user): Lease
    {
        return DB::transaction(function () use ($lease, $user): Lease {
            $lease = Lease::query()->whereKey($lease->id)->lockForUpdate()->firstOrFail();
            $this->ensureOrganization($lease);

            if ($lease->workflow_status !== LeaseWorkflowStatus::DRAFT) {
                throw new ConflictHttpException('Only a draft lease can be activated.');
            }

            Unit::query()->whereKey($lease->unit_id)->lockForUpdate()->firstOrFail();
            $this->validateActivation($lease);
            $this->ensureNoOverlap($lease);

            $lease->update([
                'workflow_status' => LeaseWorkflowStatus::ACTIVE,
                'activated_at' => now(),
                'activated_by' => $user->id,
            ]);
            $this->syncLeaseAssignments($lease);

            return $this->load($lease->refresh());
        });
    }

    public function terminate(Lease $lease, User $user, ?string $terminatedOn, ?string $reason): Lease
    {
        return DB::transaction(function () use ($lease, $terminatedOn, $reason): Lease {
            $lease = Lease::query()->whereKey($lease->id)->lockForUpdate()->firstOrFail();
            $this->ensureOrganization($lease);

            if ($lease->workflow_status !== LeaseWorkflowStatus::ACTIVE) {
                throw new ConflictHttpException('Only an active lease can be terminated.');
            }

            $date = Carbon::parse($terminatedOn ?? today()->toDateString())->startOfDay();

            if ($lease->starts_on !== null && $date->lt($lease->starts_on)) {
                throw ValidationException::withMessages(['terminated_on' => ['The termination date cannot be before the lease start date.']]);
            }

            $lease->update([
                'workflow_status' => LeaseWorkflowStatus::TERMINATED,
                'terminated_on' => $date,
                'termination_reason' => $reason,
            ]);
            $lease->contactAssignments()->update(['ends_on' => $date]);

            return $this->load($lease->refresh());
        });
    }

    public function cancel(Lease $lease): Lease
    {
        return DB::transaction(function () use ($lease): Lease {
            $lease = Lease::query()->whereKey($lease->id)->lockForUpdate()->firstOrFail();
            $this->ensureOrganization($lease);

            $isDraft = $lease->workflow_status === LeaseWorkflowStatus::DRAFT;
            $isUpcoming = $lease->workflow_status === LeaseWorkflowStatus::ACTIVE && $lease->starts_on?->isFuture();

            if (! $isDraft && ! $isUpcoming) {
                throw new ConflictHttpException('Only draft or upcoming leases can be cancelled.');
            }

            $lease->update(['workflow_status' => LeaseWorkflowStatus::CANCELLED]);
            $lease->contactAssignments()->delete();

            return $this->load($lease->refresh());
        });
    }

    /** @param array<string, mixed> $data */
    public function renew(Lease $lease, array $data): Lease
    {
        return DB::transaction(function () use ($lease, $data): Lease {
            $this->ensureOrganization($lease);
            $lease->loadMissing(['parties', 'financialTerms']);

            if ($lease->workflow_status === LeaseWorkflowStatus::CANCELLED) {
                throw new ConflictHttpException('A cancelled lease cannot be renewed.');
            }

            $parties = $data['parties'] ?? $lease->parties->map(fn ($party) => [
                'contact_id' => $party->contact_id,
                'role' => $party->role->value,
                'is_primary' => $party->is_primary,
            ])->all();
            $terms = $data['financial_terms'] ?? $lease->financialTerms->map(fn ($term) => [
                'type' => $term->type->value,
                'calculation' => $term->calculation->value,
                'amount' => $term->amount,
                'percentage' => $term->percentage,
                'currency' => $term->currency,
                'frequency' => $term->frequency->value,
                'calculation_basis' => $term->calculation_basis,
                'due_day' => $term->due_day,
                'payer_contact_id' => $term->payer_contact_id,
                'payee_contact_id' => $term->payee_contact_id,
                'is_liability' => $term->is_liability,
            ])->all();

            return $this->createDraft([
                ...$data,
                'property_id' => $lease->property_id,
                'unit_id' => $lease->unit_id,
                'currency' => $lease->currency,
                'guarantee_type' => $lease->guarantee_type?->value,
                'renewed_from_id' => $lease->id,
                'parties' => $parties,
                'financial_terms' => $terms,
            ]);
        });
    }

    /** @param array<string, mixed> $data */
    public function createAmendment(Lease $lease, array $data, User $user): LeaseAmendment
    {
        return DB::transaction(function () use ($lease, $data, $user): LeaseAmendment {
            $this->ensureOrganization($lease);

            if ($lease->workflow_status !== LeaseWorkflowStatus::ACTIVE) {
                throw new ConflictHttpException('Only an active lease can be amended.');
            }

            $amendment = $lease->amendments()->create([
                'organization_id' => $lease->organization_id,
                'created_by' => $user->id,
                'effective_on' => $data['effective_on'],
                'summary' => $data['summary'] ?? null,
                'status' => 'draft',
            ]);

            foreach ($data['financial_terms'] as $term) {
                $amendment->terms()->create($this->termAttributes($lease, [
                    ...$term,
                    'effective_from' => $data['effective_on'],
                ]));
            }

            return $amendment->load('terms');
        });
    }

    public function activateAmendment(Lease $lease, LeaseAmendment $amendment, User $user): Lease
    {
        return DB::transaction(function () use ($lease, $amendment, $user): Lease {
            $this->ensureOrganization($lease);
            abort_unless($amendment->lease_id === $lease->id, 404);
            $amendment = LeaseAmendment::query()->whereKey($amendment->id)->lockForUpdate()->firstOrFail();

            if ($amendment->status !== 'draft') {
                throw new ConflictHttpException('This amendment has already been activated.');
            }

            $amendment->load('terms');
            $closesOn = $amendment->effective_on->copy()->subDay();

            foreach ($amendment->terms as $term) {
                $lease->financialTerms()
                    ->whereKeyNot($term->id)
                    ->where('type', $term->type->value)
                    ->where(function (Builder $query) use ($amendment): void {
                        $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $amendment->effective_on);
                    })
                    ->where(function (Builder $query) use ($amendment): void {
                        $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $amendment->effective_on);
                    })
                    ->update(['effective_to' => $closesOn]);
            }

            $amendment->update(['status' => 'active', 'activated_at' => now(), 'activated_by' => $user->id]);
            $this->syncLegacySummary($lease);

            return $this->load($lease->refresh());
        });
    }

    /** @param array<string, mixed> $data */
    private function createDraft(array $data): Lease
    {
        [$property, $unit] = $this->resolveContext((int) $data['property_id'], (int) $data['unit_id']);
        $this->validatePeriod($data['starts_on'] ?? null, $data['ends_on'] ?? null);
        $organization = Organization::query()->findOrFail($this->organizationId());
        $currency = strtoupper((string) ($data['currency'] ?? $organization->currency));

        $lease = Lease::query()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'unit_id' => $unit->id,
            'renewed_from_id' => $data['renewed_from_id'] ?? null,
            'workflow_status' => LeaseWorkflowStatus::DRAFT,
            'title' => $data['title'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'currency' => $currency,
            'guarantee_type' => $data['guarantee_type'] ?? null,
            'notes' => $data['notes'] ?? null,
            'property_title_snapshot' => $property->title,
            'unit_name_snapshot' => $unit->name,
        ]);

        $this->syncParties($lease, $data['parties'] ?? []);
        $this->syncFinancialTerms($lease, $data['financial_terms'] ?? []);
        $this->syncLegacySummary($lease);

        return $this->load($lease->refresh());
    }

    /** @return array{0: Property, 1: Unit} */
    private function resolveContext(int $propertyId, int $unitId): array
    {
        $organizationId = $this->organizationId();
        $property = Property::query()->where('organization_id', $organizationId)->whereNull('archived_at')->find($propertyId);

        if ($property === null) {
            throw ValidationException::withMessages(['property_id' => ['The selected property is invalid or archived.']]);
        }

        $unit = Unit::query()->whereKey($unitId)->whereBelongsTo($property)->whereNull('archived_at')->first();

        if ($unit === null) {
            throw ValidationException::withMessages(['unit_id' => ['The selected unit must belong to the selected property and cannot be archived.']]);
        }

        return [$property, $unit];
    }

    /** @param array<int, array<string, mixed>> $parties */
    private function syncParties(Lease $lease, array $parties): void
    {
        $lease->parties()->delete();
        $seen = [];

        foreach ($parties as $party) {
            $contact = Contact::query()->where('organization_id', $lease->organization_id)->find($party['contact_id']);

            if ($contact === null) {
                throw ValidationException::withMessages(['parties' => ['Every party must belong to the active organization.']]);
            }

            $key = $contact->id.'|'.$party['role'];

            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['parties' => ['A contact cannot have the same role twice on one lease.']]);
            }
            $seen[$key] = true;

            $lease->parties()->create([
                'organization_id' => $lease->organization_id,
                'contact_id' => $contact->id,
                'role' => $party['role'],
                'is_primary' => $party['role'] === LeasePartyRole::PRIMARY_TENANT->value || (bool) ($party['is_primary'] ?? false),
                'name_snapshot' => $contact->name,
                'email_snapshot' => $contact->email,
                'starts_on' => $lease->starts_on,
                'ends_on' => $lease->ends_on,
            ]);
        }
    }

    /** @param array<int, array<string, mixed>> $terms */
    private function syncFinancialTerms(Lease $lease, array $terms): void
    {
        $lease->financialTerms()->delete();

        foreach ($terms as $term) {
            $lease->financialTerms()->create($this->termAttributes($lease, $term));
        }
    }

    /** @param array<string, mixed> $term
     *  @return array<string, mixed>
     */
    private function termAttributes(Lease $lease, array $term): array
    {
        foreach (['payer_contact_id', 'payee_contact_id'] as $field) {
            if (($term[$field] ?? null) !== null && ! Contact::query()->where('organization_id', $lease->organization_id)->whereKey($term[$field])->exists()) {
                throw ValidationException::withMessages([$field => ['The selected contact must belong to the active organization.']]);
            }
        }

        $type = LeaseFinancialTermType::from($term['type']);

        return [
            'organization_id' => $lease->organization_id,
            'lease_id' => $lease->id,
            'payer_contact_id' => $term['payer_contact_id'] ?? null,
            'payee_contact_id' => $term['payee_contact_id'] ?? null,
            'type' => $type,
            'calculation' => $term['calculation'] ?? LeaseFinancialTermCalculation::FIXED->value,
            'amount' => $term['amount'] ?? null,
            'percentage' => $term['percentage'] ?? null,
            'currency' => strtoupper((string) ($term['currency'] ?? $lease->currency)),
            'frequency' => $term['frequency'] ?? ($type === LeaseFinancialTermType::SECURITY_DEPOSIT ? LeaseFinancialTermFrequency::ONE_TIME->value : LeaseFinancialTermFrequency::MONTHLY->value),
            'calculation_basis' => $term['calculation_basis'] ?? null,
            'due_day' => $term['due_day'] ?? null,
            'effective_from' => $term['effective_from'] ?? $lease->starts_on,
            'effective_to' => $term['effective_to'] ?? $lease->ends_on,
            'is_liability' => $term['is_liability'] ?? $type === LeaseFinancialTermType::SECURITY_DEPOSIT,
        ];
    }

    private function validateActivation(Lease $lease): void
    {
        $lease->loadMissing(['property', 'parties', 'financialTerms']);
        $errors = [];

        if ($lease->property_id === null || $lease->unit_id === null) {
            $errors['unit_id'][] = 'An active lease must belong to a property and unit.';
        }
        if ($lease->starts_on === null || $lease->ends_on === null || $lease->ends_on->lte($lease->starts_on)) {
            $errors['ends_on'][] = 'An active lease requires a valid start and end date.';
        }

        $primaryTenants = $lease->parties->where('role', LeasePartyRole::PRIMARY_TENANT);
        if ($primaryTenants->count() !== 1) {
            $errors['parties'][] = 'An active lease requires exactly one primary tenant.';
        }

        $rent = $lease->financialTerms->first(fn ($term) => $term->type === LeaseFinancialTermType::RENT && $term->calculation === LeaseFinancialTermCalculation::FIXED);
        if ($rent === null || $rent->amount === null || (float) $rent->amount <= 0) {
            $errors['financial_terms'][] = 'An active lease requires a positive fixed rent term.';
        }

        if (strtoupper((string) $lease->property?->country) === 'BR') {
            if (strtoupper((string) $lease->currency) !== 'BRL') {
                $errors['currency'][] = 'Brazilian urban leases must use BRL.';
            }

            if ($lease->guarantee_type === RentalGuaranteeType::GUARANTOR
                && ! $lease->parties->contains('role', LeasePartyRole::GUARANTOR)) {
                $errors['parties'][] = 'A guarantor guarantee requires a guarantor party.';
            }

            if ($lease->guarantee_type === RentalGuaranteeType::CASH_DEPOSIT && $rent !== null) {
                $deposit = $lease->financialTerms->first(fn ($term) => $term->type === LeaseFinancialTermType::SECURITY_DEPOSIT);
                if ($deposit === null || $deposit->amount === null) {
                    $errors['financial_terms'][] = 'A cash deposit guarantee requires a security deposit term.';
                } elseif ((float) $deposit->amount > ((float) $rent->amount * 3)) {
                    $errors['financial_terms'][] = 'A Brazilian cash deposit cannot exceed three months of rent.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function ensureNoOverlap(Lease $lease): void
    {
        $overlap = Lease::query()
            ->where('unit_id', $lease->unit_id)
            ->where('workflow_status', LeaseWorkflowStatus::ACTIVE->value)
            ->whereKeyNot($lease->id)
            ->whereDate('starts_on', '<=', $lease->ends_on)
            ->whereDate('ends_on', '>=', $lease->starts_on)
            ->lockForUpdate()
            ->exists();

        if ($overlap) {
            throw new ConflictHttpException('This unit already has an active or upcoming lease for the selected period.');
        }
    }

    private function syncLeaseAssignments(Lease $lease): void
    {
        $lease->contactAssignments()->delete();

        foreach ($lease->parties as $party) {
            if ($party->contact_id === null) {
                continue;
            }

            $role = match ($party->role) {
                LeasePartyRole::PRIMARY_TENANT => ContactAssignmentRole::TENANT,
                LeasePartyRole::CO_TENANT => ContactAssignmentRole::CO_TENANT,
                LeasePartyRole::OCCUPANT => ContactAssignmentRole::OCCUPANT,
                LeasePartyRole::GUARANTOR => ContactAssignmentRole::GUARANTOR,
                LeasePartyRole::OWNER => ContactAssignmentRole::OWNER,
                LeasePartyRole::MANAGER => ContactAssignmentRole::MANAGER,
                LeasePartyRole::AGENT => ContactAssignmentRole::AGENT,
            };

            ContactAssignment::query()->create([
                'organization_id' => $lease->organization_id,
                'contact_id' => $party->contact_id,
                'property_id' => $lease->property_id,
                'unit_id' => $lease->unit_id,
                'lease_id' => $lease->id,
                'role' => $role,
                'source' => ContactAssignmentSource::LEASE,
                'is_primary' => $party->is_primary,
                'starts_on' => $lease->starts_on,
                'ends_on' => $lease->ends_on,
            ]);
        }
    }

    private function syncLegacySummary(Lease $lease): void
    {
        $lease->load(['parties', 'financialTerms']);
        $tenant = $lease->parties->firstWhere('role', LeasePartyRole::PRIMARY_TENANT);
        $rent = $lease->financialTerms->firstWhere('type', LeaseFinancialTermType::RENT);
        $deposit = $lease->financialTerms->firstWhere('type', LeaseFinancialTermType::SECURITY_DEPOSIT);
        $lease->update([
            'tenant_contact_id' => $tenant?->contact_id,
            'tenant_name_snapshot' => $tenant?->name_snapshot,
            'tenant_email_snapshot' => $tenant?->email_snapshot,
            'rent_amount' => $rent?->amount,
            'security_deposit' => $deposit?->amount,
        ]);
    }

    private function validatePeriod(?string $startsOn, ?string $endsOn): void
    {
        if ($startsOn !== null && $endsOn !== null && Carbon::parse($endsOn)->lte(Carbon::parse($startsOn))) {
            throw ValidationException::withMessages(['ends_on' => ['The end date must be after the start date.']]);
        }
    }

    private function organizationId(): int
    {
        return $this->activeOrganizationContext->id()
            ?? throw new AuthorizationException('An active organization is required.');
    }

    private function ensureOrganization(Lease $lease): void
    {
        if ($lease->organization_id !== $this->organizationId()) {
            throw new AuthorizationException;
        }
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return [
            'property',
            'unit.leases',
            'parties.contact',
            'financialTerms',
            'amendments.terms',
            'renewedFrom:id,title',
        ];
    }
}
