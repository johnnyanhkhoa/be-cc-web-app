<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Comment out or remove factory calls
        // User::factory(10)->create();

        // Only call your custom seeders
        $this->call([
            UserSeeder::class,
        ]);
    }
}
