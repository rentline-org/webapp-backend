<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Auth\AuthService;
use App\Services\User\UserProfileCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ActiveOrganizationController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    /** Store a newly created resource in storage. */
    public function store(Request $request, Organization $organization)
    {
        $user = $request->user();

        $result = $this->authService->selectOrganization($user, $organization->id);

        UserProfileCacheService::forget($user->id);

        return new JsonResponse($result, Response::HTTP_OK);
    }
}
