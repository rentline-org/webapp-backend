<?php

namespace App\Services\Listing;

use App\DTOs\Listing\ListingDTO;
use App\Models\Listing;
use App\Services\Organization\ActiveOrganizationContext;
use Illuminate\Support\Facades\DB;
use Throwable;

class ListingService
{
    /**
     * __construct
     *
     * @return void
     */
    public function __construct() {}

    public function getListing(): ?Listing
    {
        return Listing::query()->with(['organization', 'custom_listing'])->firstOrFail();
    }

    /** @throws Throwable */
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

    /** @throws Throwable */
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
