<?php

namespace App\Http\Controllers\Api\V1\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AcceptOrganizationInvitationRequest;
use App\Http\Requests\Organization\StoreOrganizationInvitationRequest;
use App\Http\Resources\Organization\OrganizationInvitationResource;
use App\Http\Resources\User\UserResource;
use App\Models\OrganizationInvitation;
use App\Services\Organization\ActiveOrganizationContext;
use App\Services\Organization\OrganizationInvitationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrganizationInvitationController extends Controller
{
    public function __construct(
        private OrganizationInvitationService $invitationService,
    ) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->canManageActiveOrganization(), Response::HTTP_FORBIDDEN);

        $organizationId = app(ActiveOrganizationContext::class)->id();
        $query = OrganizationInvitation::query()
            ->where('organization_id', $organizationId)
            ->with(['organization', 'contact', 'inviter'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            match ($request->string('status')->toString()) {
                'pending' => $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now()),
                'accepted' => $query->whereNotNull('accepted_at'),
                'revoked' => $query->whereNotNull('revoked_at'),
                'expired' => $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '<=', now()),
                default => null,
            };
        }

        return OrganizationInvitationResource::collection(
            $query->paginate($request->integer('per_page', 15))->withQueryString()
        );
    }

    public function store(StoreOrganizationInvitationRequest $request)
    {
        $invitation = $this->invitationService->create(
            $request->user(),
            app(ActiveOrganizationContext::class)->id(),
            $request->validated(),
        );

        return (new OrganizationInvitationResource($invitation))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $token): OrganizationInvitationResource
    {
        return new OrganizationInvitationResource(
            $this->invitationService->findUsableByToken($token)
        );
    }

    public function accept(AcceptOrganizationInvitationRequest $request, string $token): UserResource
    {
        $user = $this->invitationService->accept(
            $token,
            $request->validated(),
            $request->user(),
        );

        return new UserResource($user);
    }

    public function resend(Request $request, OrganizationInvitation $invitation): OrganizationInvitationResource
    {
        $this->authorizeInvitation($request, $invitation);

        return new OrganizationInvitationResource($this->invitationService->resend($invitation));
    }

    public function destroy(Request $request, OrganizationInvitation $invitation)
    {
        $this->authorizeInvitation($request, $invitation);
        $this->invitationService->revoke($invitation);

        return $this->successNoContent();
    }

    private function authorizeInvitation(Request $request, OrganizationInvitation $invitation): void
    {
        $organizationId = app(ActiveOrganizationContext::class)->id();

        abort_unless(
            $organizationId !== null
                && $invitation->organization_id === $organizationId
                && $request->user()->canManageActiveOrganization(),
            Response::HTTP_NOT_FOUND,
        );
    }
}
