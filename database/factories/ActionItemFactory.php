<?php

namespace Database\Factories;

use App\Enums\ActionItemStatus;
use App\Enums\ActionItemType;
use App\Models\ActionItem;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActionItem>
 */
class ActionItemFactory extends Factory
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
            'type' => ActionItemType::DOCUMENT_EXPIRY,
            'status' => ActionItemStatus::OPEN,
            'priority' => 'normal',
            'title' => fake()->sentence(4),
            'due_on' => now()->addDays(7)->toDateString(),
            'unique_key' => fake()->uuid(),
        ];
    }
}
