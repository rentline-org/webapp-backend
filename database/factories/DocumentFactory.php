<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'property_id' => null,
            'unit_id' => null,
            'uploaded_by' => null,
            'signed_by' => null,
            'type' => DocumentType::GENERIC,
            'title' => fake()->sentence(3),
            'purpose' => fake()->sentence(),
            'description' => fake()->optional()->paragraph(),
            'requires_signature' => false,
            'is_signed' => false,
            'signed_at' => null,
        ];
    }

    public function lease(): static
    {
        return $this->state(fn () => [
            'type' => DocumentType::LEASE,
            'requires_signature' => true,
        ]);
    }

    public function requiresSignature(): static
    {
        return $this->state(fn () => ['requires_signature' => true]);
    }
}
