<?php

namespace App\Repositories\Contracts;

use App\Models\Document;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DocumentRepositoryInterface
{
    public function paginate(int $organizationId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function create(array $attributes, ?array $leaseAttributes = null): Document;

    public function update(Document $document, array $attributes, ?array $leaseAttributes = null): Document;

    public function delete(Document $document): bool;

    public function load(Document $document): Document;
}
