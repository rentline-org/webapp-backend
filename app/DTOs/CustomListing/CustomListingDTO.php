<?php

namespace App\DTOs\CustomListing;

use App\Models\CustomListing;
use Illuminate\Http\Request;

class CustomListingDTO
{
    public function __construct(
        public ?int $listing_id,
        public string $subdomain,
        public ?string $headline,

        public bool $is_published,
        public bool $use_organization_defaults,

        public bool $show_contact_form,
        public bool $show_phone,
        public bool $show_email,

        public ?string $contact_email,
        public ?string $contact_phone,

        public ?array $languages,

        /** @var array<int, int> */
        public array $property_ids = [],
    ) {}

    public static function fromRequest(Request $request, ?CustomListing $existing = null): self
    {
        return new self(
            listing_id: $existing?->listing_id ?? $request->input('listing_id'),

            subdomain: $request->input('subdomain', $existing?->subdomain),

            headline: $request->input(
                'headline',
                $existing?->headline
            ),

            is_published: (bool) $request->input(
                'is_published',
                $existing?->is_published ?? false
            ),

            use_organization_defaults: (bool) $request->input(
                'use_organization_defaults',
                $existing?->use_organization_defaults ?? true
            ),

            show_contact_form: (bool) $request->input(
                'show_contact_form',
                $existing?->show_contact_form ?? false
            ),

            show_phone: (bool) $request->input(
                'show_phone',
                $existing?->show_phone ?? true
            ),

            show_email: (bool) $request->input(
                'show_email',
                $existing?->show_email ?? true
            ),

            contact_email: $request->input(
                'contact_email',
                $existing?->contact_email
            ),

            contact_phone: $request->input(
                'contact_phone',
                $existing?->contact_phone
            ),

            languages: $request->input(
                'languages',
                $existing?->languages
            ),

            property_ids: $request->input('property_ids', []),
        );
    }

    public function toArray(): array
    {
        return [
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
        ];
    }
}
