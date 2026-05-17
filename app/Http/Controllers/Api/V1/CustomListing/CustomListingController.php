<?php

namespace App\Http\Controllers\Api\V1\CustomListing;

use App\DTOs\CustomListing\CustomListingDTO;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CustomListing\CustomListingCreateRequest;
use App\Http\Requests\CustomListing\CustomListingUpdatePropertiesRequest;
use App\Http\Requests\CustomListing\CustomListingUpdateRequest;
use App\Http\Resources\CustomListing\CustomListingResource;
use App\Models\CustomListing;
use App\Models\Listing;
use App\Services\CustomListing\CustomListingService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class CustomListingController extends Controller
{
    public function __construct(protected CustomListingService $customListingService) {}

    /** Store a newly created resource in storage. */
    public function store(Listing $listing, CustomListingCreateRequest $request)
    {
        Gate::authorize('create', CustomListing::class);
        $validated = $request->validated();

        try {
            $newListing = $this->customListingService->createCustomListing($listing, CustomListingDTO::fromRequest($validated));
        } catch (ApiException $e) {
            return $this->respond([
                'message' => $e->getMessage(),
            ]);
        }

        return CustomListingResource::make($newListing);
    }

    public function showPublic(string $subdomain)
    {
        Gate::authorize('view-published', CustomListing::class);
        $listing = $this->customListingService->getCustomListing($subdomain);

        return CustomListingResource::make($listing);
    }

    /** Display the specified resource. */
    public function show(CustomListing $customListing)
    {
        Gate::authorize('view', $customListing);
        $customListing->load(['listing']);

        return CustomListingResource::make($customListing);
    }

    /** Update the specified resource in storage. */
    public function update(CustomListingUpdateRequest $request, CustomListing $customListing)
    {
        Gate::authorize('update', CustomListing::class);
        $validated = $request->validated();

        $updatedListing = $this->customListingService->updateWebsite($customListing, $validated);

        return CustomListingResource::make($updatedListing);
    }

    public function updateProperties(CustomListing $customListing, CustomListingUpdatePropertiesRequest $request)
    {
        Gate::authorize('update', $customListing);
        $validated = $request->validated();

        $propertyIds = $validated['property_ids'];
        $this->customListingService->updatePropertyList($customListing, $propertyIds);

        $freshListing = $customListing->refresh();
        return CustomListingResource::make($freshListing);
    }

    public function publish(CustomListing $customListing)
    {
        Gate::authorize('update', $customListing);
        $this->customListingService->publish($customListing);

        return $this->respond(null, Response::HTTP_NO_CONTENT);
    }

    /** Just set the website listing as drafted. */
    public function destroy(CustomListing $customListing)
    {
        Gate::authorize('delete', $customListing);
        $this->customListingService->draft($customListing);

        return $this->respond(null, Response::HTTP_NO_CONTENT);
    }
}
