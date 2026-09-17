<?php

namespace App\Repositories\Document;

use App\Models\Document;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class DocumentRepository implements DocumentRepositoryInterface
{
    public function paginate(int $organizationId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($organizationId, $filters)->paginate($perPage)->withQueryString();
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
            'customKind',
            'property:id,slug,title',
            'unit:id,property_id,slug,name',
            'uploader:id,name',
            'signer:id,name',
            'lease.tenant:id,name,email,phone,type',
            'properties:id,organization_id,slug,title',
            'units:id,property_id,slug,name',
            'leases',
            'parties.contact:id,user_id,name,email',
            'requiredSigners.contact:id,user_id,name,email',
            'requiredSigners.actor:id,name',
            'shares.contact:id,user_id,name,email',
            'shares.user:id,name,email',
            'versions.creator:id,name',
            'versions.primaryMedia',
            'versions.signedMedia',
            'currentVersion.primaryMedia',
            'currentVersion.signedMedia',
            'media',
        ]);
    }

    protected function query(int $organizationId, array $filters = []): Builder
    {
        $query = Document::query()
            ->where('organization_id', $organizationId)
            ->with([
                'customKind',
                'property:id,slug,title',
                'unit:id,property_id,slug,name',
                'uploader:id,name',
                'signer:id,name',
                'lease.tenant:id,name,email,phone,type',
                'properties:id,organization_id,slug,title',
                'units:id,property_id,slug,name',
                'leases',
                'parties.contact:id,user_id,name,email',
                'requiredSigners.contact:id,user_id,name,email',
                'requiredSigners.actor:id,name',
                'shares.contact:id,user_id,name,email',
                'shares.user:id,name,email',
                'versions.creator:id,name',
                'versions.primaryMedia',
                'versions.signedMedia',
                'currentVersion.primaryMedia',
                'currentVersion.signedMedia',
                'media',
            ]);

        if (! empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';

            $query->where(function (Builder $query) use ($search): void {
                $query->whereLike('title', $search)
                    ->orWhereLike('purpose', $search)
                    ->orWhereLike('description', $search)
                    ->orWhereLike('reference_number', $search)
                    ->orWhereHas('property', fn (Builder $query) => $query->whereLike('title', $search))
                    ->orWhereHas('unit', fn (Builder $query) => $query->whereLike('name', $search))
                    ->orWhereHas('parties', fn (Builder $query) => $query->whereLike('name_snapshot', $search));
            });
        }

        if (isset($filters['assigned_scope'])) {
            $scope = $filters['assigned_scope'];
            $query->where(function (Builder $query) use ($scope): void {
                $query->whereIn('property_id', $scope['broad_property_ids'])
                    ->orWhereIn('unit_id', $scope['unit_ids'])
                    ->orWhereHas('properties', fn (Builder $query) => $query
                        ->whereIn('properties.id', $scope['broad_property_ids']))
                    ->orWhereHas('units', fn (Builder $query) => $query
                        ->whereIn('units.id', $scope['unit_ids'])
                        ->orWhereIn('units.property_id', $scope['broad_property_ids']))
                    ->orWhereHas('leases', fn (Builder $query) => $query
                        ->whereIn('leases.unit_id', $scope['unit_ids'])
                        ->orWhereIn('leases.property_id', $scope['broad_property_ids']));
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['custom_kind_id'])) {
            $query->where('document_kind_id', $filters['custom_kind_id']);
        }

        if (! empty($filters['lifecycle'])) {
            $query->where('lifecycle', $filters['lifecycle']);
        }

        if (! empty($filters['property_id'])) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('property_id', $filters['property_id'])
                    ->orWhereHas('properties', fn (Builder $query) => $query->whereKey($filters['property_id']));
            });
        }

        if (! empty($filters['unit_id'])) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->where('unit_id', $filters['unit_id'])
                    ->orWhereHas('units', fn (Builder $query) => $query->whereKey($filters['unit_id']));
            });
        }

        if (! empty($filters['lease_id'])) {
            $query->whereHas('leases', fn (Builder $query) => $query->whereKey($filters['lease_id']));
        }

        if (! empty($filters['contact_id'])) {
            $query->where(function (Builder $query) use ($filters): void {
                $query->whereHas('parties', fn (Builder $query) => $query->where('contact_id', $filters['contact_id']))
                    ->orWhereHas('requiredSigners', fn (Builder $query) => $query->where('contact_id', $filters['contact_id']))
                    ->orWhereHas('shares', fn (Builder $query) => $query->active()->where('contact_id', $filters['contact_id']));
            });
        }

        if (! empty($filters['party_role'])) {
            $query->whereHas('parties', fn (Builder $query) => $query->where('role', $filters['party_role']));
        }

        if (! empty($filters['shared_with_user_id'])) {
            $query->whereHas(
                'shares',
                fn (Builder $query) => $query
                    ->active()
                    ->where('user_id', $filters['shared_with_user_id']),
            );
        }

        if (! empty($filters['expires_from'])) {
            $query->whereDate('expires_on', '>=', $filters['expires_from']);
        }

        if (! empty($filters['expires_to'])) {
            $query->whereDate('expires_on', '<=', $filters['expires_to']);
        }

        match ($filters['visibility'] ?? null) {
            'shared' => $query->whereHas('shares', fn (Builder $query) => $query->active()),
            'internal' => $query->whereDoesntHave('shares', fn (Builder $query) => $query->active()),
            default => null,
        };

        match ($filters['signature_status'] ?? null) {
            'signed' => $query->where('is_signed', true),
            'declined' => $query
                ->where('requires_signature', true)
                ->where('is_signed', false)
                ->whereHas('currentVersion.signers', fn (Builder $query) => $query->where('status', 'declined')),
            'partially_signed' => $query
                ->where('requires_signature', true)
                ->where('is_signed', false)
                ->whereHas('currentVersion.signers', fn (Builder $query) => $query->whereIn('status', ['signed', 'waived']))
                ->whereDoesntHave('currentVersion.signers', fn (Builder $query) => $query->where('status', 'declined')),
            'pending' => $query
                ->where('requires_signature', true)
                ->where('is_signed', false)
                ->whereDoesntHave('currentVersion.signers', fn (Builder $query) => $query->whereIn('status', ['signed', 'waived', 'declined'])),
            'not_required' => $query->where('requires_signature', false),
            default => null,
        };

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }
}
