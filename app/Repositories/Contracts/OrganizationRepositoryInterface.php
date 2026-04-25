<?php

namespace App\Repositories\Contracts;

use App\DTOs\Organization\OrganizationDTO;
use App\Models\Organization;

interface OrganizationRepositoryInterface
{
    public function getAllUserOrganizations(int $userId): array;

    public function findById(int $id): ?Organization;

    public function create(array $data): Organization;

    public function update(Organization $organization, OrganizationDTO $data): Organization;

    public function delete(Organization $organization): bool;

    public function assignUserToOrganization(int $userId, int $organizationId): void;

    public function removeUserFromOrganization(int $userId, int $organizationId): void;

    public function getOrganizationUsers(int $organizationId): array;

    public function updateOrganizationAvatar(Organization $organization, $avatar): Organization;
}
