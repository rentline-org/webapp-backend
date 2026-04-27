<?php

namespace Database\Factories;

use App\Enums\OrganizationPlan;
use App\Enums\TaxIDType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->company(),
            'description' => $this->faker->optional()->sentence(),

            'phone' => $this->faker->optional()->phoneNumber(),
            'email' => $this->faker->unique()->companyEmail(),
            'website' => $this->faker->optional()->url(),

            // let seeder override this when needed
            'owner_id' => User::factory(),

            'country' => 'BR',
            'state' => $this->faker->state(),
            'city' => $this->faker->city(),
            'postal_code' => $this->faker->postcode(),
            'address_line' => $this->faker->streetAddress(),

            'currency' => 'BRL',
            'timezone' => 'America/Sao_Paulo',

            'tax_id' => $this->faker->optional()->numerify('##############'),
            'tax_id_type' => $this->faker->optional()->randomElement(
                array_map(fn ($c) => $c->value, TaxIDType::cases())
            ),

            'plan' => OrganizationPlan::TRIAL->value,
            'is_plan_active' => true,

            'data_retention_until' => null,
            'is_active' => true,

            'settings' => [
                'notifications' => true,
                'theme' => 'light',
            ],

            'trial_ends_at' => now()->addDays(14),
        ];
    }
}
