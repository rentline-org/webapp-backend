<?php

namespace App\Services\Organization;

use App\DTOs\Organization\OrganizationDTO;
use App\Models\Organization;
use App\Models\User;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use Exception;

class OrganizationService
{
    /**
     * __construct
     *
     * @return void
     */
    public function __construct(
        protected OrganizationRepositoryInterface $organizationRepository
    ) {}

    public function getUserOrganizations(User $user): array
    {
        return $this->organizationRepository->getAllUserOrganizations($user->id);
    }

    public function getOrganization(int $organizationId): ?Organization
    {
        return $this->organizationRepository->findById($organizationId);
    }

    public function createOrganization(User $user, OrganizationDTO $data): Organization
    {
        if ($user->isTenant()) {
            throw new Exception('Tenants cannot create organizations.');
        }

        $newOrganization = $this->organizationRepository->create($data);

        if ($user->isLandlord()) {
            $this->organizationRepository->assignUserToOrganization($user->id, $newOrganization->id);
        }

        return $newOrganization;
    }

    public function updateOrganization(User $user, OrganizationDTO $data): Organization
    {
        if ($user->isTenant()) {
            throw new Exception('Tenants cannot update organizations.');
        }

        $existingOrganization = $this->organizationRepository->findById($data->id);

        if (! $existingOrganization->exists()) {
            throw new Exception("Organization with ID {$data->id} not found.");
        }

        $newOrganization = $this->organizationRepository->update($existingOrganization, $data);

        return $newOrganization;
    }

    public function deleteOrganization(User $user, int $id): void
    {
        if ($user->isTenant()) {
            throw new Exception('Tenants cannot delete organizations.');
        }

        $existingOrganization = $this->organizationRepository->findById($id);

        if (! $existingOrganization->exists()) {
            throw new Exception("Organization with ID {$id} not found.");
        }

        $this->organizationRepository->delete($existingOrganization);
    }
}
