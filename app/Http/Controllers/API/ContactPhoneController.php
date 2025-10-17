<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ContactPhoneService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class ContactPhoneController extends Controller
{
    protected ContactPhoneService $phoneService;

    public function __construct(ContactPhoneService $phoneService)
    {
        $this->phoneService = $phoneService;
    }

    /**
     * Get all phones for a customer
     *
     * @param int $customerId
     * @return JsonResponse
     */
    public function index(int $customerId): JsonResponse
    {
        try {
            Log::info('Get phones request received', ['customer_id' => $customerId]);

            $result = $this->phoneService->getPhones($customerId);

            // Return exact response from Maximus API
            return response()->json($result['data'], $result['status_code']);

        } catch (Exception $e) {
            Log::error('Failed to get phones', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 0,
                'data' => null,
                'message' => 'Failed to retrieve customer phones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create new phone for a customer
     *
     * @param Request $request
     * @param int $customerId
     * @return JsonResponse
     */
    public function store(Request $request, int $customerId): JsonResponse
    {
        try {
            Log::info('Create phone request received', [
                'customer_id' => $customerId,
                'data' => $request->all(),
            ]);

            // Validate request
            $validator = Validator::make($request->all(), [
                'contactType' => 'required|string|in:customer,householder,spouse,referee,guarantor',
                'contactTypeDetail' => 'nullable|string|max:255',
                'phoneNo' => 'required|string|max:50',
                'phoneRemark' => 'nullable|string',
                'isPrimary' => 'required|boolean',
                'isViber' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 0,
                    'data' => null,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $result = $this->phoneService->createPhone($customerId, $request->all());

            // Return exact response from Maximus API
            return response()->json($result['data'], $result['status_code']);

        } catch (Exception $e) {
            Log::error('Failed to create phone', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 0,
                'data' => null,
                'message' => 'Failed to create phone: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update existing phone for a customer
     *
     * @param Request $request
     * @param int $customerId
     * @param int $phoneId
     * @return JsonResponse
     */
    public function update(Request $request, int $customerId, int $phoneId): JsonResponse
    {
        try {
            Log::info('Update phone request received', [
                'customer_id' => $customerId,
                'phone_id' => $phoneId,
                'data' => $request->all(),
            ]);

            // Validate request
            $validator = Validator::make($request->all(), [
                'contactType' => 'required|string|in:customer,householder,spouse,referee,guarantor',
                'contactTypeDetail' => 'nullable|string|max:255',
                'phoneNo' => 'required|string|max:50',
                'phoneRemark' => 'nullable|string',
                'isPrimary' => 'required|boolean',
                'isViber' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 0,
                    'data' => null,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $result = $this->phoneService->updatePhone($customerId, $phoneId, $request->all());

            // Return exact response from Maximus API
            return response()->json($result['data'], $result['status_code']);

        } catch (Exception $e) {
            Log::error('Failed to update phone', [
                'customer_id' => $customerId,
                'phone_id' => $phoneId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 0,
                'data' => null,
                'message' => 'Failed to update phone: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete phone for a customer
     *
     * @param int $customerId
     * @param int $phoneId
     * @return JsonResponse
     */
    public function destroy(int $customerId, int $phoneId): JsonResponse
    {
        try {
            Log::info('Delete phone request received', [
                'customer_id' => $customerId,
                'phone_id' => $phoneId,
            ]);

            $result = $this->phoneService->deletePhone($customerId, $phoneId);

            // Return exact response from Maximus API
            return response()->json($result['data'], $result['status_code']);

        } catch (Exception $e) {
            Log::error('Failed to delete phone', [
                'customer_id' => $customerId,
                'phone_id' => $phoneId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 0,
                'data' => null,
                'message' => 'Failed to delete phone: ' . $e->getMessage(),
            ], 500);
        }
    }
}
