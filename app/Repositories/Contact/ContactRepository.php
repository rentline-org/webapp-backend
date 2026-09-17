<?php

namespace App\Repositories\Contact;

use App\Models\Contact;
use App\Repositories\Contracts\ContactRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ContactRepository implements ContactRepositoryInterface
{
    public function all(int $organizationId, array $filters = []): Collection
    {
        return $this->query($organizationId, $filters)->get();
    }

    public function findById(int $organizationId, int $id): ?Contact
    {
        return $this->query($organizationId)->find($id);
    }

    public function create(array $data, array $propertyIds = []): Contact
    {
        return DB::transaction(function () use ($data, $propertyIds): Contact {
            $contact = Contact::query()->create($data);
            $contact->properties()->sync($propertyIds);

            return $contact->load(['properties', 'assignments.property', 'assignments.unit']);
        });
    }

    public function update(Contact $contact, array $data, ?array $propertyIds = null): Contact
    {
        return DB::transaction(function () use ($contact, $data, $propertyIds): Contact {
            $contact->update($data);

            if ($propertyIds !== null) {
                $contact->properties()->sync($propertyIds);
            }

            return $contact->refresh()->load(['properties', 'assignments.property', 'assignments.unit']);
        });
    }

    public function delete(Contact $contact): bool
    {
        /** @var Contact $contact */
        return DB::transaction($contact->delete(...));
    }

    protected function query(int $organizationId, array $filters = []): Builder
    {
        $query = Contact::query()
            ->where('organization_id', $organizationId)
            ->with([
                'properties:id,slug,title',
                'assignments.property:id,slug,title',
                'assignments.unit:id,property_id,slug,name',
            ]);

        if (! empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';

            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('name', $search)
                    ->orWhereLike('email', $search)
                    ->orWhereLike('phone', $search);
            });
        }

        if (isset($filters['assigned_scope'])) {
            $scope = $filters['assigned_scope'];
            $query->where(function (Builder $query) use ($scope): void {
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
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['property_id'])) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->whereHas(
                    'properties',
                    fn (Builder $propertyQuery) => $propertyQuery->whereKey($filters['property_id'])
                )->orWhereHas(
                    'assignments',
                    fn (Builder $assignmentQuery) => $assignmentQuery->where('property_id', $filters['property_id'])
                );
            });
        }

        return $query->latest();
    }
}
