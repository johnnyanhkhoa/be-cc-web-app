<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\SendExportRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ExportController extends Controller
{
    /**
     * Test send export request to RabbitMQ
     */
    public function testExport(Request $request)
    {
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'username' => 'required|string',
                'email' => 'required|email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Prepare sample data
            $exportData = [
                'username' => $request->username,
                'email' => $request->email,
                'report_type' => 'collection_report', // Sample
                'date_from' => $request->date_from ?? now()->subDays(7)->format('Y-m-d'),
                'date_to' => $request->date_to ?? now()->format('Y-m-d'),
                'requested_at' => now()->toISOString(),
            ];

            // Dispatch job to RabbitMQ
            SendExportRequest::dispatch($exportData);

            Log::info('Export request dispatched', [
                'data' => $exportData
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Export request sent to queue successfully',
                'data' => $exportData
            ], 200);

        } catch (\Exception $e) {
            Log::error('Failed to send export request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send export request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
