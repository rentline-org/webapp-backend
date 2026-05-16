<?php

namespace App\Http\Controllers\Api\V1\Listing;

use App\DTOs\Listing\ListingDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Listing\ListingCreateRequest;
use App\Http\Requests\Listing\ListingUpdateRequest;
use App\Http\Resources\Listing\ListingResource;
use App\Models\Listing;
use App\Services\Listing\ListingService;
use Illuminate\Support\Facades\Gate;

class ListingController extends Controller
{
    public function __construct(protected ListingService $listingService) {}

    /** Display a listing of the resource. */
    public function index()
    {
        Gate::authorize('viewAny', Listing::class);

        $listingData = $this->listingService->getListing();

        return ListingResource::make($listingData);
    }

    /** Store a newly created resource in storage. */
    public function store(ListingCreateRequest $request)
    {
        Gate::authorize('create', Listing::class);
        $validated = $request->validated();

        $listing = $this->listingService->createListing(ListingDTO::fromRequest($validated));

        return ListingResource::make($listing);
    }

    /** Update the specified resource in storage. */
    public function update(ListingUpdateRequest $request, Listing $listing)
    {
        Gate::authorize('update', $listing);
        $validated = $request->validated();

        $updatedListing = $this->listingService->updateListing($listing, ListingDTO::fromRequest($validated));

        return ListingResource::make($updatedListing);
    }
}
