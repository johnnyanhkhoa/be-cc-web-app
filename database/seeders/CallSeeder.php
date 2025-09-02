<?php

namespace Database\Seeders;

use App\Models\Call;
use App\Models\User;
use Illuminate\Database\Seeder;

class CallSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test calls for development
        $this->command->info('Creating test calls...');

        // Get sample users for assignment
        $users = User::limit(5)->get();

        if ($users->count() < 2) {
            $this->command->warn('Not enough users found. Please run UserSeeder first.');
            return;
        }

        $createdBy = $users->first()->id;

        // Create 50 pending calls (ready for assignment)
        Call::factory()
            ->pending()
            ->count(50)
            ->create([
                'created_by' => $createdBy
            ]);

        // Create 30 assigned calls
        Call::factory()
            ->assigned()
            ->count(30)
            ->create([
                'created_by' => $createdBy
            ]);

        // Create 20 completed calls
        Call::factory()
            ->completed()
            ->count(20)
            ->create([
                'created_by' => $createdBy
            ]);

        $this->command->info('Created 100 test calls:');
        $this->command->info('- 50 pending calls (ready for assignment)');
        $this->command->info('- 30 assigned calls');
        $this->command->info('- 20 completed calls');

        // Show status breakdown
        $statusCounts = Call::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $this->command->table(
            ['Status', 'Count'],
            $statusCounts->map(fn($count, $status) => [$status, $count])->toArray()
        );
    }
}
