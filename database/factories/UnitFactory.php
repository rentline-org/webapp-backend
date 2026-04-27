<?php

namespace Database\Factories;

use App\Enums\UnitType;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),

            'name' => 'Unit ' . $this->faker->unique()->numberBetween(1, 200),
            'description' => $this->faker->optional()->sentence(),

            'unit_type' => $this->faker->randomElement(UnitType::cases()),
            'is_available' => true,
            'is_furnished' => $this->faker->boolean(),

            'rent_price' => $this->faker->randomFloat(2, 800, 5000),
            'sale_price' => null,

            'bedrooms' => $this->faker->numberBetween(0, 3),
            'bathrooms' => $this->faker->numberBetween(1, 2),
            'square_feet' => $this->faker->randomFloat(2, 30, 120),

            'amenities' => ['wifi'],
            'available_from' => now(),

            'is_pet_friendly' => $this->faker->boolean(),
        ];
    }
}
