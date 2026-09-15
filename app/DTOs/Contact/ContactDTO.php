<?php

namespace App\DTOs\Contact;

use App\Enums\ContactPersonType;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactDTO
{
    /** @param  array<int, int>|null  $propertyIds */
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly ContactPersonType $type,
        public readonly ?array $propertyIds,
    ) {}

    public static function fromRequest(Request $request, ?Contact $existing = null): self
    {
        $data = $request->validated();

        return new self(
            $existing?->id,
            $data['name'] ?? $existing?->name ?? '',
            array_key_exists('email', $data) ? $data['email'] : $existing?->email,
            array_key_exists('phone', $data) ? $data['phone'] : $existing?->phone,
            self::resolveType($data['type'] ?? $existing?->type),
            array_key_exists('property_ids', $data)
                ? array_map(intval(...), $data['property_ids'])
                : null,
        );
    }

    public static function fromArray(array $data, ?Contact $existing = null): self
    {
        return new self(
            $existing?->id ?? ($data['id'] ?? null),
            $data['name'] ?? $existing?->name ?? '',
            array_key_exists('email', $data) ? $data['email'] : $existing?->email,
            array_key_exists('phone', $data) ? $data['phone'] : $existing?->phone,
            self::resolveType($data['type'] ?? $existing?->type),
            array_key_exists('property_ids', $data)
                ? array_map(intval(...), $data['property_ids'])
                : null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'type' => $this->type->value,
        ];
    }

    private static function resolveType(ContactPersonType|string|null $type): ContactPersonType
    {
        if ($type instanceof ContactPersonType) {
            return $type;
        }

        return ContactPersonType::tryFrom((string) $type) ?? ContactPersonType::TENANT;
    }
}
