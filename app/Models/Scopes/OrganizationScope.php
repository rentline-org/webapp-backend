<?php

namespace App\Models\Scopes;

use App\Helpers\OrganizationHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param Builder<Model> $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $user = request()->user();

        if (! $user || $user->isSuperAdmin()) {
            return;
        }

        $orgId = app(OrganizationHelper::class)->get();

        if (! $orgId) {
            return;
        }

        $builder->where(
            $model->getTable() . '.organization_id',
            $orgId
        );
    }
}
