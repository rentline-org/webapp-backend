<?php

namespace App\Http\Middleware;

use App\Services\Organization\ActiveOrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        /**
         * Priority:
         * 1. X-Organization-Id header
         * 2. Sanctum token organization_id
         */
        $headerOrgId = $request->header('X-Organization-Id');

        // if (! $headerOrgId) {
        //     abort(403, 'Select an active organization first');
        // }

        $activeOrgId = $headerOrgId;

        /**
         * Validate organization access
         */
        if (
            $activeOrgId &&
            ! $user->organizations()->whereKey($activeOrgId)->exists()
        ) {
            abort(403, 'Invalid organization for this user.');
        }

        // app(ActiveOrganizationContext::class)->set($activeOrgId);
        $request->attributes->set('active_org_id', $activeOrgId);

        return $next($request);
    }
}
