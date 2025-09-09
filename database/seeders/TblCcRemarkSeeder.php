<?php

namespace Database\Seeders;

use App\Models\TblCcRemark;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class TblCcRemarkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            $this->command->info('Starting TblCcRemark seeder...');

            // Define all 38 remarks data
            $remarks = [
                [
                    'remarkContent' => 'Already paid in system (Early Termination / OTP)',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Call Later / Client didn\'t make ptp now who requests to call the next day',
                    'contactType' => 'rpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Can\'t Contact / No answer-Client, Household members and Ref didn\'t answer the calls',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Can\'t Contact / Power Off-All the numbers are switched off',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Change of Payment Date',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Copy / Don\'t have to call',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'CSO Pending / Branch office pending - Confirm with officer but need to update in system right away.',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Dead Case - CCO sugguested to provide family member facts and figures to office',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Exempt penalty fee with DPD & P2P Date from 1 to 7',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Fraud Case / LTO/SA/CSO/Merchant payment pending',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'LTO Pending / LTO has already collected money from client but payment is still pending in system to update',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Owner Book / Licence Pending Case',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'P2P Partial Paid / Client has promised to pay partially paid of installment',
                    'contactType' => 'rpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Partial Paid / Client has made partial payment',
                    'contactType' => 'rpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Payment pending - Agent keep the installment and hasn\'t transferred R2O yet',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'PTP / P2P with PNT Fees-Client has promised to pay with penalty fee',
                    'contactType' => 'rpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'PTP / P2P with PNT Fees-TPC has promised to pay with penalty fee instead of client',
                    'contactType' => 'tpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Reschedule',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'RPC refuse to pay / Client didn\'t accept about PTP, Reschedule, Partial Paid at all.',
                    'contactType' => 'rpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'RPC refuse to pay penalty fees.',
                    'contactType' => 'rpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'RTP / R2P - Client has reminded to pay on or before the due date',
                    'contactType' => 'rpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Technical delay - KBZ/CB Bank /K Pay /CB Pay Payment Pending - Confirm with bank\'s slip but need to update in system right away',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Technical delay - Wave /True Money /Citizen Pay /Ongo /One stop transaction error',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'TPC refuse to inform',
                    'contactType' => 'tpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'TPC will inform / Household will inform the client right away',
                    'contactType' => 'tpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'TPC will inform / Neighbour / Friend will inform the client right away',
                    'contactType' => 'tpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'TPC will inform / Reference will inform the client right away',
                    'contactType' => 'tpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Transfer To Litigation / All no are power off even first payment',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Repossess the bike',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Client have money but can\'t go outside due to war.',
                    'contactType' => 'rpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Client applied for RB which got accepted and still need to sign the contract',
                    'contactType' => 'rpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Only penalty fees left -Can\'t Contact',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Only penalty fees left -Client refuse to pay penalty fees',
                    'contactType' => 'rpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Only penalty fees left -Promise to pay penalty fees',
                    'contactType' => 'rpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'Only penalty fees left -TPC will inform the client',
                    'contactType' => 'tpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'PTP / P2P with PNT Fees-TPC has promised to pay with penalty fee instead of client',
                    'contactType' => 'tpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'TPC will inform the client',
                    'contactType' => 'tpc',
                    'remarkActive' => true,
                ],
                [
                    'remarkContent' => 'No need to call for collection today (It\'s still in the process of reapplying for cash loan).',
                    'contactType' => 'all',
                    'remarkActive' => true,
                ],
            ];

            $this->command->info('Inserting ' . count($remarks) . ' remarks...');

            // Use database transaction for data integrity
            DB::transaction(function () use ($remarks) {
                $successCount = 0;
                $skipCount = 0;

                foreach ($remarks as $index => $remarkData) {
                    try {
                        // Check if remark already exists (avoid duplicates)
                        $existingRemark = TblCcRemark::where('remarkContent', $remarkData['remarkContent'])
                                                   ->where('contactType', $remarkData['contactType'])
                                                   ->first();

                        if ($existingRemark) {
                            $this->command->warn("Remark '{$remarkData['remarkContent']}' already exists, skipping...");
                            $skipCount++;
                            continue;
                        }

                        // Create new remark
                        $remark = TblCcRemark::create($remarkData);

                        $this->command->info(sprintf(
                            "[%d/%d] Created: [%s] %s (ID: %d)",
                            $index + 1,
                            count($remarks),
                            strtoupper($remark->contactType),
                            substr($remark->remarkContent, 0, 50) . '...',
                            $remark->remarkId
                        ));

                        $successCount++;

                    } catch (Exception $e) {
                        $this->command->error("Failed to create remark: " . $e->getMessage());
                        throw $e; // Re-throw to rollback transaction
                    }
                }

                $this->command->info("Transaction completed successfully!");
                $this->command->info("✅ Successfully inserted: {$successCount} remarks");
                if ($skipCount > 0) {
                    $this->command->info("⏭️  Skipped (already exists): {$skipCount} remarks");
                }
            });

            // Display summary
            $this->command->newLine();
            $this->command->info('📊 SEEDER SUMMARY:');
            $this->command->info('==================');

            $totalRemarks = TblCcRemark::count();
            $activeRemarks = TblCcRemark::where('remarkActive', true)->count();

            // Count by contact type
            $allRemarks = TblCcRemark::where('contactType', 'all')->count();
            $rpcRemarks = TblCcRemark::where('contactType', 'rpc')->count();
            $tpcRemarks = TblCcRemark::where('contactType', 'tpc')->count();

            $this->command->info("Total remarks in database: {$totalRemarks}");
            $this->command->info("Active remarks: {$activeRemarks}");
            $this->command->info("By contact type:");
            $this->command->info("  - ALL: {$allRemarks} remarks");
            $this->command->info("  - RPC: {$rpcRemarks} remarks");
            $this->command->info("  - TPC: {$tpcRemarks} remarks");

            $this->command->newLine();
            $this->command->info('🎉 TblCcRemark seeder completed successfully!');

            // Display sample data
            $this->command->info('📋 Sample inserted data:');
            $sampleRemarks = TblCcRemark::take(3)->get(['remarkId', 'remarkContent', 'contactType', 'remarkActive']);

            foreach ($sampleRemarks as $sample) {
                $status = $sample->remarkActive ? '✅ Active' : '❌ Inactive';
                $content = substr($sample->remarkContent, 0, 40) . '...';
                $this->command->info("  - ID: {$sample->remarkId} | [{$sample->contactType}] {$content} | {$status}");
            }

        } catch (Exception $e) {
            $this->command->error('❌ TblCcRemark seeder failed!');
            $this->command->error('Error: ' . $e->getMessage());
            $this->command->error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());

            // Log the error for debugging
            Log::error('TblCcRemark seeder failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e; // Re-throw to stop the seeding process
        }
    }

    /**
     * Method to verify seeded data
     */
    public function verify(): void
    {
        $this->command->info('🔍 Verifying seeded data...');

        $expectedCount = 38; // We expect 38 remarks total
        $actualCount = TblCcRemark::count();

        if ($actualCount === $expectedCount) {
            $this->command->info("✅ Verification passed! Found {$actualCount} remarks as expected.");
        } else {
            $this->command->error("❌ Verification failed! Expected {$expectedCount} but found {$actualCount} remarks.");
        }

        // Check contact type distribution
        $contactTypes = TblCcRemark::select('contactType')
                                  ->selectRaw('COUNT(*) as count')
                                  ->groupBy('contactType')
                                  ->get();

        $this->command->info('📊 Contact types distribution:');
        foreach ($contactTypes as $type) {
            $this->command->info("  - {$type->contactType}: {$type->count} remarks");
        }
    }
}
