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

            'is_available' => true,
            'is_furnished' => $this->faker->boolean(),

            'rent_price' => $this->faker->optional()->randomFloat(2, 500, 8000),
            'sale_price' => $this->faker->optional()->randomFloat(2, 100000, 900000),
            'buy_price' => $this->faker->optional()->randomFloat(2, 100000, 900000),

            'bedrooms' => $this->faker->numberBetween(1, 5),
            'bathrooms' => $this->faker->numberBetween(1, 3),
            'square_feet' => $this->faker->randomFloat(2, 40, 300),

            'amenities' => ['wifi', 'parking'],
            'available_from' => now(),

            'is_pet_friendly' => $this->faker->boolean(),

            'sale_types' => ['rent'],
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
