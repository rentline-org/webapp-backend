<?php

namespace Database\Factories;

use App\Enums\PropertyType;
use App\Models\Organization;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(3);

        return [
            'organization_id' => Organization::factory(),

            'slug' => Str::slug($title),

            'title' => $title,
            'description' => $this->faker->optional()->paragraph(),

            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postal_code' => $this->faker->postcode(),
            'country' => 'BR',

            'property_type' => $this->faker->randomElement(
                array_map(fn ($c) => $c->value, PropertyType::cases())
            ),

        ];
    }

    /*
    |--------------------------------------------------------------------------
    | States (important)
    |--------------------------------------------------------------------------
    */

    public function apartment(): static
    {
        return $this->state(fn () => [
            'property_type' => PropertyType::APARTMENT->value,
        ]);
    }

    public function house(): static
    {
        return $this->state(fn () => [
            'property_type' => PropertyType::HOUSE->value,
        ]);
    }
}
