<?php

namespace App\Http\Resources\Organization;

use App\Enums\MediaCollection;
use App\Http\Resources\Listing\ListingResource;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class OrganizationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isMediaLoaded = ! $this->whenLoaded('media') instanceof MissingValue;

        $data = [
            ...parent::toArray($request),
            'properties_count' => $this->whenCounted('properties'),
            'users' => UserResource::collection($this->whenLoaded('users')),
            'listing' => ListingResource::make($this->whenLoaded('listing')),
        ];

        if ($isMediaLoaded) {
            $data['logo'] = $this->getFirstMedia(MediaCollection::ORGANIZATION->value)?->getUrl();
        }

        return $data;
    }
}
