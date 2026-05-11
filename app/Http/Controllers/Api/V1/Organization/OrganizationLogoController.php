<?php

namespace App\Http\Controllers\Api\V1\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\OrganizationLogoUpdateRequest;
use App\Http\Resources\Organization\OrganizationResource;
use App\Services\Organization\ActiveOrganizationContext;
use App\Services\Organization\OrganizationService;
use App\Services\User\UserProfileCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class OrganizationLogoController extends Controller
{
    public function __construct(
        protected OrganizationService $organizationService,
    ) {}

    public function update(OrganizationLogoUpdateRequest $request)
    {
        $request->validated();
        $user = $request->user();
        $orgId = app(ActiveOrganizationContext::class)->id();

        $organization = $this->organizationService->getOrganization($orgId);

        Gate::authorize('update', $organization);
        $updatedOrg = $this->organizationService->updateOrganizationLogo($organization, $request->logo);

        UserProfileCacheService::forget($user->id);

        return OrganizationResource::make($updatedOrg);
    }

    public function delete(Request $request)
    {
        $user = $request->user();
        $orgId = app(ActiveOrganizationContext::class)->id();
        $organization = $this->organizationService->getOrganization($orgId);

        $this->organizationService->removeOrganizationLogo($organization);

        UserProfileCacheService::forget($user->id);

        return $this->respond(null, Response::HTTP_NO_CONTENT);
    }
}
