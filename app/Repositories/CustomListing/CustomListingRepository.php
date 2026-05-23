<?php

namespace App\Repositories\CustomListing;

use App\Models\CustomListing;
use App\Models\Listing;
use App\Models\Property;
use App\Repositories\Contracts\CustomListingRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Repository for handling custom listing persistence and property syncing.
 */
class CustomListingRepository implements CustomListingRepositoryInterface
{
    public function findPublishedByDomain(string $subdomain): CustomListing
    {
        return CustomListing::query()
            ->where('subdomain', $subdomain)
            ->where('is_published', true)
            ->with([
                'listing.organization',
                'properties.units',
                'properties.media',
                'properties.units.media',
                'listing.organization.media',
            ])
            ->firstOrFail();
    }

    /** Find a custom listing by its domain. */
    public function findByDomain(string $subdomain): CustomListing
    {
        return CustomListing::query()
            ->where('subdomain', $subdomain)
            ->withCount('properties')
            ->firstOrFail();
    }

    /**
     * Create a new custom listing.
     *
     * @param array<string, mixed> $data
     *
     * @throws Throwable
     */
    public function create(Listing $listing, array $data): CustomListing
    {
        return DB::transaction(function () use ($listing, $data): CustomListing {
            /** @var CustomListing $customListing */
            $customListing = $listing->customListing()->create($data);

            return $customListing->fresh() ?? $customListing;
        });
    }

    public function saveAsDraft(CustomListing $customListing): void
    {
        $customListing->update([
            'is_published' => false,
        ]);
    }

    /** Publish a custom listing. */
    public function saveAsPublished(CustomListing $customListing): void
    {
        $customListing->update([
            'is_published' => true,
        ]);
    }

    /**
     * Sync the selected properties for a custom listing.
     *
     * This will:
     * - attach the given properties to the custom listing
     * - detach any previously attached properties that are no longer selected
     *
     * Properties outside the listing's organization are ignored.
     *
     * @param array<int, int|string> $propertyIds
     *
     * @throws Throwable
     */
    public function syncProperties(CustomListing $customListing, array $propertyIds): void
    {
        DB::transaction(function () use ($customListing, $propertyIds): void {
            $selectedPropertyIds = $this->resolveOrganizationPropertyIds(
                $customListing,
                $propertyIds
            );

            $this->unsyncProperties(
                $customListing,
                $selectedPropertyIds
            );

            $this->attachProperties(
                $customListing,
                $selectedPropertyIds
            );
        });
    }

    /**
     * Update an existing custom listing.
     *
     * @param array<string, mixed> $data
     *
     * @throws Throwable
     */
    public function update(CustomListing $customListing, array $data): ?CustomListing
    {
        return DB::transaction(function () use ($customListing, $data): CustomListing {

            $customListing->fill($data);
            $customListing->save();

            return $customListing->fresh(['listing']) ?? $customListing;
        });
    }

    protected function resolveOrganizationPropertyIds(
        CustomListing $customListing,
        array $propertyIds
    ): array {
        $customListing->loadMissing('listing');

        $organizationId = $customListing->listing?->organization_id;

        if ($organizationId === null) {
            return [];
        }

        return Property::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $propertyIds)
            ->pluck('id')
            ->all();
    }

    protected function attachProperties(
        CustomListing $customListing,
        array $selectedPropertyIds
    ): void {
        $customListing->loadMissing('listing');

        $organizationId = $customListing->listing?->organization_id;

        if ($organizationId === null || empty($selectedPropertyIds)) {
            return;
        }

        Property::query()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $selectedPropertyIds)
            ->where(function ($query) use ($customListing) {
                $query->whereNull('custom_listing_id')
                    ->orWhere('custom_listing_id', '!=', $customListing->id);
            })
            ->update([
                'custom_listing_id' => $customListing->id,
            ]);
    }

    protected function unsyncProperties(
        CustomListing $customListing,
        array $selectedPropertyIds
    ): void {
        $customListing->loadMissing('listing');

        $organizationId = $customListing->listing?->organization_id;

        if ($organizationId === null) {
            return;
        }

        Property::query()
            ->where('organization_id', $organizationId)
            ->where('custom_listing_id', $customListing->id)
            ->whereNotIn('id', $selectedPropertyIds)
            ->update([
                'custom_listing_id' => null,
            ]);
    }
}
