<?php

namespace App\DTOs\Contact;

use App\Enums\ContactIdentityKind;
use App\Enums\ContactPersonType;
use App\Enums\ContactTaxIdType;
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
        public readonly ContactIdentityKind $identityKind,
        public readonly string $preferredLocale,
        public readonly ?int $userId,
        public readonly ?ContactTaxIdType $taxIdType,
        public readonly ?string $taxId,
        public readonly bool $taxIdProvided,
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
            self::resolveIdentityKind($data['identity_kind'] ?? $existing?->identity_kind),
            $data['preferred_locale'] ?? $existing?->preferred_locale ?? 'en',
            array_key_exists('user_id', $data) ? $data['user_id'] : $existing?->user_id,
            self::resolveTaxIdType($data['tax_id_type'] ?? $existing?->tax_id_type),
            $data['tax_id'] ?? null,
            array_key_exists('tax_id', $data),
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
            self::resolveIdentityKind($data['identity_kind'] ?? $existing?->identity_kind),
            $data['preferred_locale'] ?? $existing?->preferred_locale ?? 'en',
            array_key_exists('user_id', $data) ? $data['user_id'] : $existing?->user_id,
            self::resolveTaxIdType($data['tax_id_type'] ?? $existing?->tax_id_type),
            $data['tax_id'] ?? null,
            array_key_exists('tax_id', $data),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'type' => $this->type->value,
            'identity_kind' => $this->identityKind->value,
            'preferred_locale' => $this->preferredLocale,
            'user_id' => $this->userId,
            'tax_id_type' => $this->taxIdType?->value,
        ];
    }

    private static function resolveType(ContactPersonType|string|null $type): ContactPersonType
    {
        if ($type instanceof ContactPersonType) {
            return $type;
        }

        return ContactPersonType::tryFrom((string) $type) ?? ContactPersonType::TENANT;
    }

    private static function resolveIdentityKind(ContactIdentityKind|string|null $kind): ContactIdentityKind
    {
        return $kind instanceof ContactIdentityKind
            ? $kind
            : (ContactIdentityKind::tryFrom((string) $kind) ?? ContactIdentityKind::PERSON);
    }

    private static function resolveTaxIdType(ContactTaxIdType|string|null $type): ?ContactTaxIdType
    {
        if ($type === null || $type === '') {
            return null;
        }

        return $type instanceof ContactTaxIdType
            ? $type
            : ContactTaxIdType::tryFrom((string) $type);
    }
}
