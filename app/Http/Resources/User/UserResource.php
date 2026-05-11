<?php

namespace App\Http\Resources\User;

use App\Enums\MediaCollection;
use App\Http\Resources\Organization\OrganizationResource;
use App\Http\Resources\Permission\PermissionResource;
use App\Http\Resources\Role\RoleSlimResource;
use App\Models\Organization;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class UserResource extends JsonResource
{
    protected ?Organization $resolvedActiveOrg = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isPermissionLoaded = ! $this->whenLoaded('permissions') instanceof MissingValue;
        $isMediaLoaded = ! $this->whenLoaded('media') instanceof MissingValue;

        $activeOrg = $this->resolveActiveOrganization();

        $data = [
            ...parent::toArray($request),
            'is_deleted' => (bool) $this->deleted_at,
            'roleNames' => $this->whenLoaded('roles') ? $this->getRoleNames() : [],
            'roles' => RoleSlimResource::collection($this->whenLoaded('roles')),
            'creator' => UserSlimResource::make($this->whenLoaded('creator')),

            'active_organization' => $activeOrg
                ? new OrganizationResource($activeOrg)
                : null,

            'organizations' => OrganizationResource::collection($this->whenLoaded('organizations')),
        ];

        if ($isPermissionLoaded) {
            $data['permissions'] = PermissionResource::collection($this->getPermissionsViaRoles());
        }

        if ($isMediaLoaded) {
            unset($data['media']);
            $data['photo'] = $this->getFirstMedia(MediaCollection::PROFILE->value)?->getUrl();
        }

        return $data;
    }

    protected function resolveActiveOrganization(): ?Organization
    {
        $orgId = app(ActiveOrganizationContext::class)->id();

        if ($orgId) {
            return Organization::query()->where('id', $orgId)->with(['media'])->first();
        }

        return null;
    }
}
