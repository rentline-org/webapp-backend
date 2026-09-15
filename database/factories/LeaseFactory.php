<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Lease;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lease> */
class LeaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_id' => Document::factory()->lease(),
            'tenant_contact_id' => null,
            'property_title_snapshot' => fake()->streetName(),
            'unit_name_snapshot' => 'Unit '.fake()->numberBetween(1, 20),
            'tenant_name_snapshot' => fake()->name(),
            'tenant_email_snapshot' => fake()->safeEmail(),
            'starts_on' => today(),
            'ends_on' => today()->addYear(),
            'rent_amount' => fake()->randomFloat(2, 500, 8000),
            'currency' => 'BRL',
            'security_deposit' => fake()->optional()->randomFloat(2, 500, 16000),
            'notes' => fake()->optional()->paragraph(),
        ];
    }
}
