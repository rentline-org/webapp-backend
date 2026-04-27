<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'user_name' => 'super.admin',
            'email' => 'superadmin@ims.com',
            'email_verified_at' => now(),
            'phone' => '01700000000',
            'phone_verified_at' => now(),
            'password' => Hash::make('123456'),
            'is_active' => UserStatus::ACTIVE,
            'last_login_at' => now(),
        ]);

        $superAdmin->assignRole(UserRole::SUPER_ADMIN);

        // Danny (landlord)
        $danny = User::create([
            'first_name' => 'Danny',
            'last_name' => 'K',
            'name' => 'Danny K',
            'user_name' => 'danny.k',
            'email' => 'kruger.dkk@gmail.com',
            'email_verified_at' => now(),
            'phone' => '5545999955224',
            'phone_verified_at' => now(),
            'password' => Hash::make('qwerty123'),
            'is_active' => UserStatus::ACTIVE,
            'last_login_at' => now(),
        ]);

        $danny->assignRole(UserRole::LANDLORD);

        // Extra users
        $verifiedUser = User::factory()->create();
        $verifiedUser->assignRole(UserRole::LANDLORD);

        echo "\n\nCreated verified user:{$verifiedUser->email}\n\n";

        $unverifiedUser = User::create([
            'first_name' => 'Kai',
            'last_name' => 'Kruger',
            'name' => 'Kai Kruger',
            'user_name' => 'kai.kruger',
            'email' => 'danniellkkruger@gmail.com',
            'email_verified_at' => null,
            'phone' => '5545999955334',
            'phone_verified_at' => null,
            'password' => Hash::make('qwerty123'),
            'is_active' => UserStatus::ACTIVE,
            'last_login_at' => now(),

        ]);

        $unverifiedUser->assignRole(UserRole::LANDLORD);

        echo "\nCreated unverified user:{$unverifiedUser->email}\n\n";
    }
}
