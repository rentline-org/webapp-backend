<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $danny = User::where('email', '=', 'kruger.dkk@gmail.com')->firstOrFail();
        $otherVerifiedLandlord = User::where('email', '!=', 'kruger.dkk@gmail.com')
            ->where('email_verified_at', '!=', null)->get();

        $organizations = Organization::factory()
            ->count(2)
            ->create([
                'owner_id' => $danny->id,
            ]);

        $danny->organizations()->sync($organizations->pluck('id'));

        foreach ($otherVerifiedLandlord as $landlord) {
            $randomOrgs = Organization::factory()->count(1)->create([
                'owner_id' => $landlord->id,
            ]);
            $landlord->organizations()->sync($randomOrgs->pluck('id'));
        }
    }
}
