<?php

namespace App\Http\Resources\Organization;

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
            'users' => UserResource::collection($this->whenLoaded('users')),
        ];

        if ($isMediaLoaded) {
            $data['avatar'] = $this->getFirstMediaUrl('avatar')->getUrl();
        }

        return $data;
    }
}
