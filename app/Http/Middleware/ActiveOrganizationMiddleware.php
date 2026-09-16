<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationMemberStatus;
use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActiveOrganizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $headerOrgId = filter_var($request->header('X-Organization-Id'), FILTER_VALIDATE_INT);
        $activeOrgId = $headerOrgId === false || $headerOrgId < 1 ? null : $headerOrgId;

        if (
            $activeOrgId &&
            ! $user->isSuperAdmin() &&
            ! $user->organizations()
                ->whereKey($activeOrgId)
                ->wherePivot('status', OrganizationMemberStatus::ACTIVE->value)
                ->exists()
        ) {
            abort(403, 'Invalid organization for this user.');
        }

        if ($activeOrgId && $user->isSuperAdmin() && ! Organization::query()->whereKey($activeOrgId)->exists()) {
            abort(404, 'Organization not found.');
        }

        $request->attributes->set('active_org_id', $activeOrgId);

        return $next($request);
    }
}
