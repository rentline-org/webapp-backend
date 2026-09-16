<?php

namespace App\Repositories\Organization;

use App\DTOs\Organization\OrganizationDTO;
use App\Enums\MediaCollection;
use App\Enums\OrganizationMemberRole;
use App\Enums\OrganizationMemberStatus;
use App\Models\Organization;
use App\Models\User;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class OrganizationRepository implements OrganizationRepositoryInterface
{
    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /** {@inheritDoc} */
    public function assignUserToOrganization(int $userId, int $organizationId): void
    {
        DB::transaction(function () use ($userId, $organizationId) {
            $user = User::findOrFail($userId);
            $organization = Organization::findOrFail($organizationId);

            $user->organizations()->syncWithoutDetaching([
                $organization->id => [
                    'role' => OrganizationMemberRole::OWNER->value,
                    'status' => OrganizationMemberStatus::ACTIVE->value,
                    'accepted_at' => now(),
                ],
            ]);
        });
    }

    /** {}
     * @throws \Throwable
     */
    public function create(array $data): Organization
    {
        return DB::transaction(fn () => Organization::query()->create($data));
    }

    /** @throws \Throwable */
    public function delete(Organization $organization): bool
    {
        /** @var Model $organization */
        DB::transaction(function () use ($organization) {
            $organization->users()->detach();

            return $organization->delete();
        });

        return true;
    }

    /** {@inheritDoc} */
    public function findById(int $id): Organization
    {
        return Organization::query()
            ->with(['users', 'contacts', 'media'])
            ->findOrFail($id);
    }

    /**
     * Retrieves all organizations associated with the specified user.
     *
     * @param int $userId The ID of the user whose organizations are to be retrieved.
     *
     * @throws ModelNotFoundException If the user with the given ID is not found.
     *
     * @return Organization[]
     */
    /** {@inheritDoc} */
    public function getAllUserOrganizations(int $userId): array
    {
        $user = User::query()->findOrFail($userId);

        return $user->organizations()
            ->wherePivot('status', OrganizationMemberStatus::ACTIVE->value)
            ->withCount('properties')
            ->get()
            ->all();
    }

    /** {@inheritDoc}
     * @throws \Throwable
     */
    public function getOrganizationUsers(int $organizationId): array
    {
        return DB::transaction(function () use ($organizationId) {
            $organization = Organization::findOrFail($organizationId);

            return $organization->users()->get()->all();
        });
    }

    /** {@inheritDoc}
     * @throws \Throwable
     */
    public function removeUserFromOrganization(int $userId, int $organizationId): void
    {
        DB::transaction(function () use ($userId, $organizationId) {
            $user = User::findOrFail($userId);

            $user->organizations()->detach($organizationId);
        });
    }

    /** {@inheritDoc} */
    public function update(Organization $organization, OrganizationDTO $data): Organization
    {
        return DB::transaction(function () use ($organization, $data) {
            $organization->update($data->toArray());

            return $organization->refresh();
        });
    }

    /** {@inheritDoc}
     * @throws \Throwable
     */
    public function updateOrganizationAvatar(Organization $organization, $avatar): Organization
    {
        return DB::transaction(function () use ($organization, $avatar) {
            $organization
                ->addMedia($avatar)
                ->toMediaCollection('avatar');

            return $organization->refresh();
        });
    }

    /** {@inheritDoc} */
    public function updateLogo(Organization $organization, $logo): Organization
    {

        $organization->addMedia($logo)->toMediaCollection(MediaCollection::ORGANIZATION->value);
        $organization->save();

        return $organization->refresh();
    }
}
