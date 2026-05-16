<?php

namespace App\Repositories\Contracts;

use App\Models\CustomListing;
use App\Models\Listing;

interface CustomListingRepositoryInterface
{
    public function findByDomain(string $subdomain, bool $publishedOnly = false): ?CustomListing;

    public function create(Listing $listing, array $data): CustomListing;

    public function update(CustomListing $customListing, array $data): ?CustomListing;

    public function saveAsPublished(CustomListing $customListing): void;

    public function saveAsDraft(CustomListing $customListing): void;

    public function syncProperties(CustomListing $customListing, array $propertyIds): void;
}
