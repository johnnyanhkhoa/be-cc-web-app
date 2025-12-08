<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DailyPhoneCollectionExport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendDailyPhoneCollectionReport extends Command
{
    protected $signature = 'report:send-daily-phone-collection {--date= : Report date} {--email= : Recipient email}';
    protected $description = 'Send daily phone collection report to QC team';

    public function handle()
    {
        try {
            // Get yesterday's date (Myanmar timezone)
            $reportDate = $this->option('date') ?? Carbon::yesterday('Asia/Yangon')->toDateString();

            $this->info("Generating phone collection report for: {$reportDate}");

            // Generate Excel file
            $fileName = "Phone_Collection_Report_{$reportDate}.xlsx";
            $storagePath = "exports/{$fileName}";
            $fullPath = storage_path("app/{$storagePath}");

            // Create exports directory if not exists
            if (!file_exists(storage_path('app/exports'))) {
                mkdir(storage_path('app/exports'), 0755, true);
            }

            $this->info("Generating Excel content...");
            $excelContent = Excel::raw(new DailyPhoneCollectionExport($reportDate), \Maatwebsite\Excel\Excel::XLSX);

            $this->info("Writing to file: {$fullPath}");
            file_put_contents($fullPath, $excelContent);

            $this->info("Excel file generated: {$fileName}");
            $this->info("File size: " . filesize($fullPath) . " bytes");

            // Verify file exists
            if (!file_exists($fullPath)) {
                throw new \Exception("Excel file was not created at {$fullPath}");
            }

            // Send email
            $recipient = $this->option('email') ?? 'ayethi@r2omm.com';

            Mail::raw("Dear QC Team,\n\nPlease find attached the Phone Collection Report for {$reportDate}.\n\nBest regards,\nR2O CC System", function($message) use ($recipient, $fullPath, $fileName, $reportDate) {
                $message->to($recipient)
                    ->subject("Phone Collection Report - {$reportDate}")
                    ->attach($fullPath, [
                        'as' => $fileName,
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    ]);
            });

            $this->info("Email sent successfully to {$recipient}");

            // Delete file after sending
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            Log::info('Daily phone collection report sent', [
                'date' => $reportDate,
                'recipient' => $recipient
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to send report: {$e->getMessage()}");

            Log::error('Failed to send daily phone collection report', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }
}
