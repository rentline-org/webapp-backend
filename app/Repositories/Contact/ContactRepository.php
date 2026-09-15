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

            return $contact->load('properties');
        });
    }

    public function update(Contact $contact, array $data, ?array $propertyIds = null): Contact
    {
        return DB::transaction(function () use ($contact, $data, $propertyIds): Contact {
            $contact->update($data);

            if ($propertyIds !== null) {
                $contact->properties()->sync($propertyIds);
            }

            return $contact->refresh()->load('properties');
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
            ->with(['properties:id,slug,title']);

        if (! empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';

            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('name', $search)
                    ->orWhereLike('email', $search)
                    ->orWhereLike('phone', $search);
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['property_id'])) {
            $query->whereHas(
                'properties',
                fn (Builder $query) => $query->whereKey($filters['property_id'])
            );
        }

        return $query->latest();
    }
}
