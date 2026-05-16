<?php

namespace App\Http\Resources\Listing;

use App\Http\Resources\CustomListing\CustomListingResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'type' => $this->type?->value ?? $this->type,

            'custom_listing' => CustomListingResource::make(
                $this->whenLoaded('customListing')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
