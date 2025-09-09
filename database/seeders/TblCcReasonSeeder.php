<?php

namespace Database\Seeders;

use App\Models\TblCcReason;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Exception;

class TblCcReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            // Clear existing data (optional - remove if you want to keep existing data)
            // CcReason::truncate();

            $this->command->info('Starting CcReason seeder...');

            // Define the reasons data with all 16 items
            $reasons = [
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Lost job',
                    'reasonActive' => true,
                    'reasonRemark' => 'Customer lost their job and cannot make payment due to unemployment',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Bank is close, can\'t withdraw money',
                    'reasonActive' => true,
                    'reasonRemark' => 'Bank closure or restricted banking hours preventing customer from accessing funds',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Business is good but no payment channel',
                    'reasonActive' => true,
                    'reasonRemark' => 'Customer has income but lacks accessible payment methods or channels',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Have money in Wave Acc but can\'t transfer because of internet cut off',
                    'reasonActive' => true,
                    'reasonRemark' => 'Internet connectivity issues preventing Wave money transfer to complete payment',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Have no money',
                    'reasonActive' => true,
                    'reasonRemark' => 'Customer currently has no funds available for payment',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Can\'t go outside by coup',
                    'reasonActive' => true,
                    'reasonRemark' => 'Political situation and coup restrictions preventing customer from making payment',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Don\'t want to pay in this situation',
                    'reasonActive' => true,
                    'reasonRemark' => 'Customer refuses to pay due to current circumstances or unwillingness',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Ask for Rescheduling and reject to reschedule',
                    'reasonActive' => true,
                    'reasonRemark' => 'Customer requested payment rescheduling but rejected the proposed reschedule terms',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Very less income because of coup',
                    'reasonActive' => true,
                    'reasonRemark' => 'Significantly reduced income due to political instability and coup impact on business',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Transaction Error',
                    'reasonActive' => true,
                    'reasonRemark' => 'Technical issues or errors occurred during payment transaction process',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Plate // Wheel tax // Copy of OB delay',
                    'reasonActive' => true,
                    'reasonRemark' => 'Administrative delays with vehicle registration, taxes, or official documents affecting payment ability',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Flee from civil war',
                    'reasonActive' => true,
                    'reasonRemark' => 'Customer displaced or relocated due to civil conflict and war situation',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Wave, One Stop agent are banned and can\'t come to office',
                    'reasonActive' => true,
                    'reasonRemark' => 'Payment service agents (Wave, One Stop) are restricted or banned, unable to process payments',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Client ask Litigation to collect',
                    'reasonActive' => true,
                    'reasonRemark' => 'Customer specifically requests that the case be handled through legal litigation process',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Lock down, shop are close, can\'t work because of Covid',
                    'reasonActive' => true,
                    'reasonRemark' => 'COVID-19 lockdown restrictions causing business closures and preventing work, affecting payment ability',
                ],
                [
                    'reasonType' => 'Not Paying',
                    'reasonName' => 'Duplicate Assign With Litigation Field Visit',
                    'reasonActive' => true,
                    'reasonRemark' => 'Case has been duplicated or already assigned to litigation team for field visit collection',
                ],
            ];

            $this->command->info('Inserting ' . count($reasons) . ' reasons...');

            // Use database transaction for data integrity
            DB::transaction(function () use ($reasons) {
                $successCount = 0;
                $skipCount = 0;

                foreach ($reasons as $index => $reasonData) {
                    try {
                        // Check if reason already exists (avoid duplicates)
                        $existingReason = TblCcReason::where('reasonType', $reasonData['reasonType'])
                                                 ->where('reasonName', $reasonData['reasonName'])
                                                 ->first();

                        if ($existingReason) {
                            $this->command->warn("Reason '{$reasonData['reasonName']}' already exists, skipping...");
                            $skipCount++;
                            continue;
                        }

                        // Create new reason
                        $reason = TblCcReason::create($reasonData);

                        $this->command->info(sprintf(
                            "[%d/%d] Created: %s - %s (ID: %d)",
                            $index + 1,
                            count($reasons),
                            $reason->reasonType,
                            $reason->reasonName,
                            $reason->reasonId
                        ));

                        $successCount++;

                    } catch (Exception $e) {
                        $this->command->error("Failed to create reason '{$reasonData['reasonName']}': " . $e->getMessage());
                        throw $e; // Re-throw to rollback transaction
                    }
                }

                $this->command->info("Transaction completed successfully!");
                $this->command->info("✅ Successfully inserted: {$successCount} reasons");
                if ($skipCount > 0) {
                    $this->command->info("⏭️  Skipped (already exists): {$skipCount} reasons");
                }
            });

            // Display summary
            $this->command->newLine();
            $this->command->info('📊 SEEDER SUMMARY:');
            $this->command->info('==================');

            $totalReasons = TblCcReason::count();
            $activeReasons = TblCcReason::where('reasonActive', true)->count();
            $notPayingReasons = TblCcReason::where('reasonType', 'Not Paying')->count();

            $this->command->info("Total reasons in database: {$totalReasons}");
            $this->command->info("Active reasons: {$activeReasons}");
            $this->command->info("'Not Paying' type reasons: {$notPayingReasons}");

            $this->command->newLine();
            $this->command->info('🎉 CcReason seeder completed successfully!');

            // Display sample data
            $this->command->info('📋 Sample inserted data:');
            $sampleReasons = TblCcReason::where('reasonType', 'Not Paying')
                                   ->take(3)
                                   ->get(['reasonId', 'reasonName', 'reasonActive']);

            foreach ($sampleReasons as $sample) {
                $status = $sample->reasonActive ? '✅ Active' : '❌ Inactive';
                $this->command->info("  - ID: {$sample->reasonId} | {$sample->reasonName} | {$status}");
            }

        } catch (Exception $e) {
            $this->command->error('❌ CcReason seeder failed!');
            $this->command->error('Error: ' . $e->getMessage());
            $this->command->error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            // Log the error for debugging
            logger()->error('CcReason seeder failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e; // Re-throw to stop the seeding process
        }
    }

    /**
     * Additional method to seed only specific reason types
     *
     * @param string $reasonType
     * @return void
     */
    public function seedByType(string $reasonType = 'Not Paying'): void
    {
        $this->command->info("Seeding reasons for type: {$reasonType}");

        // You can extend this method to handle different reason types
        // For now, we only have 'Not Paying' type
        if ($reasonType === 'Not Paying') {
            $this->run();
        } else {
            $this->command->warn("No predefined reasons for type: {$reasonType}");
        }
    }

    /**
     * Method to verify seeded data
     *
     * @return void
     */
    public function verify(): void
    {
        $this->command->info('🔍 Verifying seeded data...');

        $expectedCount = 16; // We expect 16 'Not Paying' reasons
        $actualCount = TblCcReason::where('reasonType', 'Not Paying')->count();

        if ($actualCount === $expectedCount) {
            $this->command->info("✅ Verification passed! Found {$actualCount} 'Not Paying' reasons as expected.");
        } else {
            $this->command->error("❌ Verification failed! Expected {$expectedCount} but found {$actualCount} 'Not Paying' reasons.");
        }

        // Check for any inactive reasons
        $inactiveCount = TblCcReason::where('reasonActive', false)->count();
        if ($inactiveCount > 0) {
            $this->command->warn("⚠️  Found {$inactiveCount} inactive reasons.");
        }

        // Display reason types distribution
        $reasonTypes = TblCcReason::select('reasonType')
                              ->selectRaw('COUNT(*) as count')
                              ->groupBy('reasonType')
                              ->get();

        $this->command->info('📊 Reason types distribution:');
        foreach ($reasonTypes as $type) {
            $this->command->info("  - {$type->reasonType}: {$type->count} reasons");
        }
    }
}
