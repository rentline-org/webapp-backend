<?php

namespace App\Http\Controllers\Api\V1\Organization;

use App\DTOs\Organization\OrganizationDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\OrganizationInsertUpdateRequest;
use App\Http\Resources\Organization\OrganizationResource;
use App\Models\Organization;
use App\Services\Auth\AuthService;
use App\Services\Organization\OrganizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class OrganizationController extends Controller
{
    public function __construct(
        protected OrganizationService $organizationService,
        protected AuthService $authService
    ) {}

    /** Display a listing of the resource. */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Organization::class);
        $organizations = $this->organizationService->getUserOrganizations($request->user());

        return OrganizationResource::collection($organizations);
    }

    /** Store a newly created resource in storage. */
    public function store(OrganizationInsertUpdateRequest $request)
    {
        $request->validated();

        $user = $request->user();

        $setActive = $request->get('setActive', false);
        unset($request['setActive']);

        $organizationDTO = OrganizationDTO::fromRequest($request);
        $newOrganization = $this->organizationService->createOrganization($user, $organizationDTO);

        if ($setActive) {
            $this->authService->selectOrganization($user, $newOrganization->id);
        }

        return new OrganizationResource($newOrganization);
    }

    /** Display the specified resource. */
    public function show(Organization $organization)
    {
        Gate::authorize('view', $organization);

        $existingOrganization = $this->organizationService->getOrganization($organization->id);

        return new OrganizationResource($existingOrganization);
    }

    /** Update the specified resource in storage. */
    public function update(OrganizationInsertUpdateRequest $request, Organization $organization)
    {
        $request->validated();
        $user = $request->user();

        $organizationDTO = OrganizationDTO::fromRequest($request, $organization);
        $updatedOrganization = $this->organizationService->updateOrganization($user, $organizationDTO);

        return new OrganizationResource($updatedOrganization);
    }

    /** Remove the specified resource from storage. */
    public function destroy(Organization $organization)
    {
        $user = request()->user();
        $this->organizationService->deleteOrganization($user, $organization->id);

        return $this->respond(['message' => 'Organization deleted successfully.'], Response::HTTP_NO_CONTENT);
    }
}
