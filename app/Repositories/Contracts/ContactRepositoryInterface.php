<?php

namespace App\Repositories\Contracts;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Collection;

interface ContactRepositoryInterface
{
    public function all(int $organizationId, array $filters = []): Collection;

    public function findById(int $organizationId, int $id): ?Contact;

    /** @param array<int, int> $propertyIds */
    public function create(array $data, array $propertyIds = []): Contact;

    /** @param array<int, int>|null $propertyIds */
    public function update(Contact $contact, array $data, ?array $propertyIds = null): Contact;

    public function delete(Contact $contact): bool;
}
