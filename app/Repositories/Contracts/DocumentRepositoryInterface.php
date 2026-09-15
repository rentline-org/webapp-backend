<?php

namespace App\Repositories\Contracts;

use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;

interface DocumentRepositoryInterface
{
    public function all(int $organizationId, array $filters = []): Collection;

    public function create(array $attributes, ?array $leaseAttributes = null): Document;

    public function update(Document $document, array $attributes, ?array $leaseAttributes = null): Document;

    public function delete(Document $document): bool;

    public function load(Document $document): Document;
}
