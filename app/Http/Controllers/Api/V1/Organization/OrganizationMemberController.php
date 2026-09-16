<?php

namespace App\Http\Controllers\Api\V1\Organization;

use App\Enums\OrganizationMemberRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateOrganizationMemberRequest;
use App\Http\Resources\Organization\OrganizationMemberResource;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\User;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class OrganizationMemberController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->canManageActiveOrganization(), Response::HTTP_FORBIDDEN);

        $organization = Organization::query()->findOrFail(app(ActiveOrganizationContext::class)->id());
        $members = $organization->users()
            ->orderBy('name')
            ->orderBy('users.id')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return OrganizationMemberResource::collection($members);
    }

    public function update(UpdateOrganizationMemberRequest $request, int $member): OrganizationMemberResource
    {
        [$organization, $user] = $this->resolveMembership($member);

        if ((int) $organization->owner_id === $user->id) {
            throw ValidationException::withMessages([
                'role' => __('messages.membership.owner_protected'),
            ]);
        }

        $data = $request->validated();

        if (($data['role'] ?? null) === OrganizationMemberRole::TENANT->value) {
            $hasLinkedContact = Contact::withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->exists();

            if (! $hasLinkedContact) {
                throw ValidationException::withMessages([
                    'role' => __('messages.membership.tenant_contact_required'),
                ]);
            }
        }

        $organization->users()->updateExistingPivot($user->id, $data);
        $user = $organization->users()->findOrFail($user->id);

        return new OrganizationMemberResource($user);
    }

    public function destroy(Request $request, int $member)
    {
        abort_unless($request->user()->canManageActiveOrganization(), Response::HTTP_FORBIDDEN);

        [$organization, $user] = $this->resolveMembership($member);

        if ((int) $organization->owner_id === $user->id) {
            throw ValidationException::withMessages([
                'member' => __('messages.membership.owner_protected'),
            ]);
        }

        $organization->users()->detach($user->id);

        return $this->successNoContent();
    }

    /** @return array{Organization, User} */
    private function resolveMembership(int $memberId): array
    {
        $organization = Organization::query()->findOrFail(app(ActiveOrganizationContext::class)->id());
        $user = $organization->users()->findOrFail($memberId);

        return [$organization, $user];
    }
}
