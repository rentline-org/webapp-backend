<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class OrganizationScope implements Scope
{
    /** Apply the scope to a given Eloquent query builder. */
    public function apply(Builder $builder, Model $model): void
    {
        $orgId = Auth::user()?->currentAccessToken()?->organization_id;
        $isSuperAdmin = Auth::user()?->isSuperAdmin();

        if ($orgId && ! $isSuperAdmin) {
            /** @var Model $modelInstance */
            $modelInstance = $builder->getModel();

            $builder->where(
                $modelInstance->getTable() . '.organization_id',
                $orgId
            );
        }
    }
}
