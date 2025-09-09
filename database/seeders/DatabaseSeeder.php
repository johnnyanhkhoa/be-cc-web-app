<?php

namespace Database\Seeders;

use App\Models\TblCcReason;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding process...');
        $this->command->newLine();

        // Comment out factory calls for production seeding
        // User::factory(10)->create();
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // Call all seeders in proper order
        $this->call([
            UserSeeder::class,
            TblCcReasonSeeder::class,
            // Add more seeders here as you create them
            // CcRemarkSeeder::class,
            // CcScriptSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('🎉 Database seeding completed successfully!');

        // Display seeding summary
        $this->displaySeedingSummary();
    }

    /**
     * Display a summary of seeded data
     */
    private function displaySeedingSummary(): void
    {
        $this->command->info('📊 SEEDING SUMMARY:');
        $this->command->info('==================');

        try {
            // Users summary
            $userCount = \App\Models\User::count();
            $activeUserCount = \App\Models\User::where('is_active', true)->count();
            $this->command->info("👥 Users: {$userCount} total ({$activeUserCount} active)");

            // CcReason summary
            $reasonCount = TblCcReason::count();
            $activeReasonCount = TblCcReason::where('reasonActive', true)->count();
            $this->command->info("📝 CcReasons: {$reasonCount} total ({$activeReasonCount} active)");

            // TblCcRemark summary
            $remarkCount = \App\Models\TblCcRemark::count();
            $activeRemarkCount = \App\Models\TblCcRemark::where('remarkActive', true)->count();
            $this->command->info("💬 TblCcRemarks: {$remarkCount} total ({$activeRemarkCount} active)");

        } catch (\Exception $e) {
            $this->command->warn('Could not generate seeding summary: ' . $e->getMessage());
        }

        $this->command->newLine();
    }

    /**
     * Seed only specific tables
     * Usage: php artisan db:seed --class=DatabaseSeeder --method=seedUsers
     */
    public function seedUsers(): void
    {
        $this->command->info('🌱 Seeding users only...');
        $this->call([UserSeeder::class]);
    }

    /**
     * Seed only TblCcReason table
     * Usage: php artisan db:seed --class=DatabaseSeeder --method=seedReasons
     */
    public function seedReasons(): void
    {
        $this->command->info('🌱 Seeding CC reasons only...');
        $this->call([TblCcReasonSeeder::class]);
    }

    /**
     * Seed all Call Center related tables
     */
    public function seedCallCenter(): void
    {
        $this->command->info('🌱 Seeding Call Center tables...');
        $this->call([
            TblCcReasonSeeder::class,
            // Add other CC seeders here when created
            // TblCcRemarkSeeder::class,
            // TblCcScriptSeeder::class,
        ]);
    }
}
