<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds to create the primary Super Administrator.
     */
    public function run(): void
    {
        $email = env('INITIAL_ADMIN_EMAIL', 'admin@croydoncollegeofexcellence.co.uk');
        $username = env('INITIAL_ADMIN_USERNAME', 'admin');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name'              => env('INITIAL_ADMIN_NAME', 'Super Administrator'),
                'username'          => $username,
                'email'             => $email,
                'password'          => env('INITIAL_ADMIN_PASSWORD', 'password'),
                'role'              => 'super_admin',
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
