<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::all();

        foreach ($organizations as $org) {
            Property::factory()
                ->count(3)
                ->for($org)
                ->apartment()
                ->has(Unit::factory()->count(5), 'units')
                ->create();

            Property::factory()
                ->count(2)
                ->for($org)
                ->house()
                ->create();
        }
    }
}
