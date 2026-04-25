<?php

namespace App\Http\Middleware;

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
        $token = $user?->currentAccessToken();

        $activeOrgId = $token?->organization_id;

        // Always set it — even if null
        $request->attributes->set('active_organization_id', $activeOrgId);

        if ($activeOrgId && ! $user->organizations()->whereKey($activeOrgId)->exists()) {
            abort(403, 'Invalid organization for this user.');
        }

        return $next($request);
    }
}
