<?php

namespace App\Repositories\Document;

use App\Models\Document;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DocumentRepository implements DocumentRepositoryInterface
{
    public function all(int $organizationId, array $filters = []): Collection
    {
        return $this->query($organizationId, $filters)->get();
    }

    public function create(array $attributes, ?array $leaseAttributes = null): Document
    {
        return DB::transaction(function () use ($attributes, $leaseAttributes): Document {
            $document = Document::query()->create($attributes);

            if ($leaseAttributes !== null) {
                $document->lease()->create($leaseAttributes);
            }

            return $this->load($document);
        });
    }

    public function update(Document $document, array $attributes, ?array $leaseAttributes = null): Document
    {
        return DB::transaction(function () use ($document, $attributes, $leaseAttributes): Document {
            $document->update($attributes);

            if ($leaseAttributes !== null) {
                $document->lease()->updateOrCreate([], $leaseAttributes);
            }

            return $this->load($document->refresh());
        });
    }

    public function delete(Document $document): bool
    {
        return DB::transaction($document->delete(...));
    }

    public function load(Document $document): Document
    {
        return $document->load([
            'property:id,slug,title',
            'unit:id,property_id,slug,name',
            'uploader:id,name',
            'signer:id,name',
            'lease.tenant:id,name,email,phone,type',
            'media',
        ]);
    }

    protected function query(int $organizationId, array $filters = []): Builder
    {
        $query = Document::query()
            ->where('organization_id', $organizationId)
            ->with([
                'property:id,slug,title',
                'unit:id,property_id,slug,name',
                'uploader:id,name',
                'signer:id,name',
                'lease.tenant:id,name,email,phone,type',
                'media',
            ]);

        if (! empty($filters['search'])) {
            $search = '%'.trim($filters['search']).'%';

            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('title', $search)
                    ->orWhereLike('purpose', $search)
                    ->orWhereLike('description', $search)
                    ->orWhereHas('property', fn (Builder $query) => $query->whereLike('title', $search))
                    ->orWhereHas('unit', fn (Builder $query) => $query->whereLike('name', $search))
                    ->orWhereHas('lease.tenant', fn (Builder $query) => $query->whereLike('name', $search));
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['property_id'])) {
            $query->where('property_id', $filters['property_id']);
        }

        if (! empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }

        match ($filters['signature_status'] ?? null) {
            'signed' => $query->where('is_signed', true),
            'pending' => $query->where('requires_signature', true)->where('is_signed', false),
            'not_required' => $query->where('requires_signature', false),
            default => null,
        };

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }
}
