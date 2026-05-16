<?php

namespace App\Services\Listing;

use App\DTOs\Listing\ListingDTO;
use App\Models\Listing;
use App\Services\Organization\ActiveOrganizationContext;
use DB;

class ListingService
{
    /**
     * __construct
     *
     * @return void
     */
    public function __construct() {}

    public function getListing()
    {
        return Listing::query()->with(['organization', 'custom_listing'])->firstOrFail();
    }

    public function createListing(ListingDTO $listingDTO): Listing
    {
        $activeOrgId = app(ActiveOrganizationContext::class)->id();
        $data = [
            ...$listingDTO->toArray(),
            'organization_id' => $activeOrgId,
        ];

        return DB::transaction(function () use ($data): Listing {
            $listing = Listing::query()->create($data);

            return $listing->load(['organization']);
        });
    }

    public function updateListing(Listing $listing, ListingDTO $listingDTO): Listing
    {
        $activeOrgId = app(ActiveOrganizationContext::class)->id();
        $data = [
            ...$listingDTO->toArray(),
            'organization_id' => $activeOrgId,
        ];

        return DB::transaction(function () use (
            $listing,
            $data
        ): Listing {
            $listing->update(
                $data
            );

            return $listing->load([
                'organization',
                'customListing',
            ]);
        });
    }
}
