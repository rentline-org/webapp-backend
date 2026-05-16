<?php

namespace App\DTOs\Listing;

use App\Enums\ListingType;
use App\Models\Listing;
use Illuminate\Http\Request;

class ListingDTO
{
    public function __construct(
        public readonly ?ListingType $type = null,
    ) {}

    public static function fromRequest(Request $request, ?Listing $existing = null): self
    {
        return new self(
            type: isset($request['type'])
                ? ListingType::from($request['type'])
                : $existing?->type,
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type?->value,
        ];
    }
}
