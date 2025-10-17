<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCustomerPhoneRequest;
use App\Models\TblCcCustomerPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class TblCcCustomerPhoneController extends Controller
{
    /**
     * Create a new customer phone contact
     *
     * @param CreateCustomerPhoneRequest $request
     * @return JsonResponse
     */
    public function store(CreateCustomerPhoneRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            Log::info('Creating customer phone contact', [
                'phone_no' => $validated['phoneNo'] ?? null,
                'customer_name' => $validated['customerName'] ?? null,
                'contact_type' => $validated['contactType'] ?? null,
                'contact_type_detail' => $validated['contactTypeDetail'] ?? null, // ✅ ADDED
                'created_by' => $validated['createdBy'],
            ]);

            DB::beginTransaction();

            // ✅ Create instance và tắt timestamps
            $customerPhone = new TblCcCustomerPhone();
            $customerPhone->timestamps = false;

            // Set data
            foreach ($validated as $key => $value) {
                $customerPhone->$key = $value;
            }

            // Set createdAt manually
            $customerPhone->createdAt = now();

            $customerPhone->save();

            // Bật lại timestamps
            $customerPhone->timestamps = true;

            DB::commit();

            Log::info('Customer phone created successfully', [
                'phone_id' => $customerPhone->phoneId,
                'phone_no' => $customerPhone->phoneNo,
                'contact_type' => $customerPhone->contactType,
                'contact_type_detail' => $customerPhone->contactTypeDetail, // ✅ ADDED
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer phone contact created successfully',
                'data' => [
                    'phoneId' => $customerPhone->phoneId,
                    'prospectId' => $customerPhone->prospectId,
                    'customerId' => $customerPhone->customerId,
                    'householderId' => $customerPhone->householderId,
                    'refereeId' => $customerPhone->refereeId,
                    'phoneNo' => $customerPhone->phoneNo,
                    'contactType' => $customerPhone->contactType,
                    'contactTypeDetail' => $customerPhone->contactTypeDetail, // ✅ ADDED
                    'phoneStatus' => $customerPhone->phoneStatus,
                    'phoneType' => $customerPhone->phoneType,
                    'isPrimary' => $customerPhone->isPrimary,
                    'isViber' => $customerPhone->isViber,
                    'phoneRemark' => $customerPhone->phoneRemark,
                    'customerName' => $customerPhone->customerName,
                    'phoneCollectionId' => $customerPhone->phoneCollectionId,
                    'createdAt' => $customerPhone->createdAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'createdBy' => $customerPhone->createdBy,
                    'updatedAt' => $customerPhone->updatedAt?->utc()->format('Y-m-d\TH:i:s\Z'),
                    'updatedBy' => $customerPhone->updatedBy,
                ]
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create customer phone', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->validated()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer phone contact',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
