<?php

namespace App\DTOs\Organization;

use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?string $address,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $website,
        public readonly ?int $numberOfProperties,
    ) {}

    public static function fromRequest(Request $request, ?Organization $existing = null): self
    {
        return new self(
            $existing?->id,
            $request->input('title'),
            $request->input('description'),
            $request->input('address'),
            $request->input('phone'),
            $request->input('email'),
            $request->input('website'),
            $request->has('number_of_properties')
                ? (int) $request->input('number_of_properties')
                : null,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? null,
            $data['title'] ?? null,
            $data['description'] ?? null,
            $data['address'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['website'] ?? null,
            isset($data['number_of_properties'])
                ? (int) $data['number_of_properties']
                : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'number_of_properties' => $this->numberOfProperties,
        ], fn ($value) => ! is_null($value));
    }
}
