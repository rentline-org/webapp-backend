<?php

namespace App\Services\CustomListing;

use App\DTOs\CustomListing\CustomListingDTO;
use App\Enums\ListingType;
use App\Models\CustomListing;
use App\Models\Listing;
use App\Repositories\Contracts\CustomListingRepositoryInterface;
use App\Services\Organization\OrganizationService;
use LogicException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CustomListingService
{
    /**
     * __construct
     *
     * @return void
     */
    public function __construct(protected CustomListingRepositoryInterface $customListingRepository, protected OrganizationService $organizationService) {}

    public function getPublicListingByDomain(string $subdomain): CustomListing
    {
        return $this->customListingRepository->findByDomain($subdomain, true);
    }

    public function getCustomListing(string $subdomain): CustomListing
    {
        return $this->customListingRepository->findByDomain($subdomain);
    }

    public function createCustomListing(Listing $listing, CustomListingDTO $customListingDTO): CustomListing
    {
        if ($listing->type !== ListingType::WEBSITE) {
            throw new UnprocessableEntityHttpException("Invalid Listing type, make sure it's of type 'website'");
        }

        $basePayload = $customListingDTO->toArray();
        $mutationPayload = $customListingDTO->use_organization_defaults
            ? $this->setOrganizationDefaults($basePayload)
            : $basePayload;

        $selectedProperties = $customListingDTO->property_ids;
        $websiteListing = $this->customListingRepository->create($listing, $mutationPayload);

        if (! empty($selectedProperties)) {
            $this->updatePropertyList($websiteListing, $selectedProperties);
        }

        return $websiteListing;
    }

    public function updateWebsite(CustomListing $customListing, CustomListingDTO $customListingDTO): CustomListing
    {
        $basePayload = $customListingDTO->toArray();
        $mutationPayload = $customListingDTO->use_organization_defaults
            ? $this->setOrganizationDefaults($basePayload)
            : $basePayload;

        return $this->customListingRepository->update($customListing, $mutationPayload);
    }

    public function updatePropertyList(CustomListing $customListing, array $propertyIds)
    {
        return $this->customListingRepository->syncProperties($customListing, $propertyIds);
    }

    /**
     * Publish a custom listing.
     *
     *
     * @throws LogicException
     */
    public function publish(CustomListing $customListing): CustomListing
    {
        if ($customListing->is_published) {
            throw new LogicException(
                'This custom listing is already published.'
            );
        }

        $this->customListingRepository
            ->saveAsPublished($customListing);

        return $customListing->refresh();
    }

    /**
     * Save a custom listing as draft.
     *
     *
     * @throws LogicException
     */
    public function draft(CustomListing $customListing): CustomListing
    {
        if (! $customListing->is_published) {
            throw new LogicException(
                'This custom listing is already a draft.'
            );
        }

        $this->customListingRepository
            ->saveAsDraft($customListing);

        return $customListing->refresh();
    }

    private function setOrganizationDefaults(array $payload): array
    {
        $activeOrg = $this->organizationService->getResolvedActiveOrg();

        return array_merge($payload, [
            'contact_phone' => $activeOrg->phone,
            'contact_email' => $activeOrg->email,
        ]);
    }
}
