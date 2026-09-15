<?php

namespace Database\Factories;

use App\Enums\ContactPersonType;
use App\Models\Contact;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'type' => fake()->randomElement(ContactPersonType::cases()),
        ];
    }

    public function tenant(): static
    {
        return $this->state(fn () => ['type' => ContactPersonType::TENANT]);
    }

    public function agent(): static
    {
        return $this->state(fn () => ['type' => ContactPersonType::AGENT]);
    }

    public function owner(): static
    {
        return $this->state(fn () => ['type' => ContactPersonType::OWNER]);
    }
}
