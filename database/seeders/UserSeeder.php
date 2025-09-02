<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test users for development
        $testUsers = [
            [
                'auth_user_id' => '1',
                'email' => 'superadmin@gmail.com',
                'username' => 'superadmin915',
                'user_full_name' => 'Super Admin',
                'is_active' => true,
            ],
            [
                'auth_user_id' => '3',
                'email' => 'staff1@test.com',
                'username' => 'Staff User 1',
                'user_full_name' => 'Staff User 1 Full Name',
                'is_active' => true,
            ],
            [
                'auth_user_id' => '4',
                'email' => 'staff2@test.com',
                'username' => 'Staff User 2',
                'user_full_name' => 'Staff User 2 Full Name',
                'is_active' => true,
            ],
        ];

        foreach ($testUsers as $userData) {
            User::create($userData);
        }
    }
}
