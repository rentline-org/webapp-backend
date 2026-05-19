<?php

namespace App\Http\Resources\CustomListing;

use App\Http\Resources\Property\PropertyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomListingResource extends JsonResource
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
            'listing_id' => $this->listing_id,

            'subdomain' => $this->subdomain,
            'headline' => $this->headline,

            'is_published' => $this->is_published,

            'use_organization_defaults' => $this->use_organization_defaults,

            'show_contact_form' => $this->show_contact_form,
            'show_phone' => $this->show_phone,
            'show_email' => $this->show_email,

            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,

            'languages' => $this->languages,
            'properties_count' => $this->whenCounted('properties'),
            'properties' => PropertyResource::collection(
                $this->whenLoaded('properties')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
