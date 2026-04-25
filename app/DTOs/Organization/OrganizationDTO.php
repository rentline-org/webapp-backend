<?php

namespace App\DTOs\Organization;

use App\Enums\OrganizationPlan;
use App\Enums\TaxIDType;
use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $website,
        public readonly ?int $ownerId,
        public readonly ?string $country,
        public readonly ?string $state,
        public readonly ?string $city,
        public readonly ?string $postalCode,
        public readonly ?string $addressLine,
        public readonly ?string $currency,
        public readonly ?string $timezone,
        public readonly ?string $taxId,
        public readonly ?TaxIDType $taxIdType,
        public readonly ?OrganizationPlan $plan,
        public readonly ?bool $isPlanActive,
        public readonly ?string $dataRetentionUntil,
        public readonly ?bool $isActive,
        public readonly ?array $settings,
        public readonly ?string $trialEndsAt,
    ) {}

    public static function fromRequest(Request $request, ?Organization $existing = null): self
    {
        return new self(
            $existing?->id,
            $request->input('title'),
            $request->input('description'),
            $request->input('phone'),
            $request->input('email'),
            $request->input('website'),
            $request->integer('owner_id') ?: $existing?->owner_id,
            $request->input('country'),
            $request->input('state'),
            $request->input('city'),
            $request->input('postal_code'),
            $request->input('address_line'),
            $request->input('currency', 'BRL'),
            $request->input('timezone', 'America/Sao_Paulo'),
            $request->input('tax_id'),
            $request->filled('tax_id_type')
                ? TaxIDType::from($request->input('tax_id_type'))
                : null,
            $request->filled('plan')
                ? OrganizationPlan::from($request->input('plan'))
                : null,
            $request->has('is_plan_active')
                ? $request->boolean('is_plan_active')
                : null,
            $request->input('data_retention_until'),
            $request->has('is_active')
                ? $request->boolean('is_active')
                : null,
            $request->input('settings'),
            $request->input('trial_ends_at'),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'] ?? null,
            $data['title'] ?? null,
            $data['description'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['website'] ?? null,
            $data['owner_id'] ?? null,
            $data['country'] ?? null,
            $data['state'] ?? null,
            $data['city'] ?? null,
            $data['postal_code'] ?? null,
            $data['address_line'] ?? null,
            $data['currency'] ?? null,
            $data['timezone'] ?? null,
            $data['tax_id'] ?? null,
            isset($data['tax_id_type']) ? TaxIDType::from($data['tax_id_type']) : null,
            isset($data['plan']) ? OrganizationPlan::from($data['plan']) : null,
            $data['is_plan_active'] ?? null,
            $data['data_retention_until'] ?? null,
            $data['is_active'] ?? null,
            $data['settings'] ?? null,
            $data['trial_ends_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'owner_id' => $this->ownerId,
            'country' => $this->country,
            'state' => $this->state,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'address_line' => $this->addressLine,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'tax_id' => $this->taxId,
            'tax_id_type' => $this->taxIdType?->value,
            'plan' => $this->plan?->value,
            'is_plan_active' => $this->isPlanActive,
            'data_retention_until' => $this->dataRetentionUntil,
            'is_active' => $this->isActive,
            'settings' => $this->settings,
            'trial_ends_at' => $this->trialEndsAt,
        ], fn ($value) => ! is_null($value));
    }
}
