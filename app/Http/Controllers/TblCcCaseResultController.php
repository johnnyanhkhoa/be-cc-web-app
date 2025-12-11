<?php

namespace App\Http\Controllers;

use App\Models\TblCcCaseResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class TblCcCaseResultController extends Controller
{
    /**
     * GET /api/case-results
     * List all case results with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = TblCcCaseResult::query()->whereNull('deletedAt');

            // Filters
            if ($request->has('batchId')) {
                $query->where('batchId', $request->batchId);
            }

            if ($request->has('contactType')) {
                $query->where('contactType', $request->contactType);
            }

            if ($request->has('caseResultActive')) {
                $query->where('caseResultActive', $request->boolean('caseResultActive'));
            }

            $caseResults = $query->with('batch:batchId,batchName')
                ->orderBy('caseResultName')
                ->get([
                    'caseResultId',
                    'caseResultName',
                    'caseResultRemark',
                    'contactType',
                    'batchId',
                    'requiredField',
                    'caseResultActive',
                    'createdAt',
                    'createdBy',
                    'updatedAt',
                    'updatedBy',
                    'deactivatedAt',
                    'deactivatedBy'
                ]);

            // Append batchName to each result
            $caseResults->each(function($result) {
                $result->batchName = $result->batch->batchName ?? null;
                unset($result->batch);
            });

            return response()->json([
                'success' => true,
                'message' => 'Case results retrieved successfully',
                'data' => $caseResults
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve case results', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve case results',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/case-results/{id}
     * Get case result by ID
     */
    public function show(int $id): JsonResponse
    {
        try {
            $caseResult = TblCcCaseResult::with('batch:batchId,batchName')
                ->whereNull('deletedAt')
                ->find($id);

            if ($caseResult) {
                $caseResult->batchName = $caseResult->batch->batchName ?? null;
                unset($caseResult->batch);
            }

            return response()->json([
                'success' => true,
                'message' => 'Case result retrieved successfully',
                'data' => $caseResult
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve case result', [
                'case_result_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve case result',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/case-results
     * Create new case result
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'caseResultName' => 'required|string|max:255',
                'caseResultRemark' => 'nullable|string',
                'contactType' => 'required|string|max:255',
                'batchId' => 'required|integer|exists:tbl_CcBatch,batchId',
                'requiredField' => 'nullable|array',
                'caseResultActive' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $caseResult = TblCcCaseResult::create([
                'caseResultName' => $request->caseResultName,
                'caseResultRemark' => $request->caseResultRemark,
                'contactType' => $request->contactType,
                'batchId' => $request->batchId,
                'requiredField' => $request->requiredField,
                'caseResultActive' => $request->input('caseResultActive', true),
                'createdAt' => now(),
                'createdBy' => $request->user_id ?? 1
            ]);

            DB::commit();

            Log::info('Case result created', [
                'case_result_id' => $caseResult->caseResultId,
                'created_by' => $request->user_id ?? 1
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Case result created successfully',
                'data' => $caseResult
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to create case result', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create case result',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/case-results/{id}
     * Update case result
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $caseResult = TblCcCaseResult::whereNull('deletedAt')->find($id);

            if (!$caseResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case result not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'caseResultName' => 'required|string|max:255',
                'caseResultRemark' => 'nullable|string',
                'contactType' => 'required|string|max:255',
                'batchId' => 'required|integer|exists:tbl_CcBatch,batchId',
                'requiredField' => 'nullable|array',
                'caseResultActive' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $caseResult->update([
                'caseResultName' => $request->caseResultName,
                'caseResultRemark' => $request->caseResultRemark,
                'contactType' => $request->contactType,
                'batchId' => $request->batchId,
                'requiredField' => $request->requiredField,
                'caseResultActive' => $request->caseResultActive,
                'updatedAt' => now(),
                'updatedBy' => $request->user_id ?? 1
            ]);

            DB::commit();

            Log::info('Case result updated', [
                'case_result_id' => $id,
                'updated_by' => $request->user_id ?? 1
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Case result updated successfully',
                'data' => $caseResult
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to update case result', [
                'case_result_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update case result',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/case-results/{id}
     * Soft delete case result
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $caseResult = TblCcCaseResult::whereNull('deletedAt')->find($id);

            if (!$caseResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case result not found'
                ], 404);
            }

            DB::beginTransaction();

            // Soft delete
            $caseResult->delete();  // Sets deletedAt automatically

            // Update deletedBy
            $caseResult->deletedBy = $request->user_id ?? 1;
            $caseResult->save();

            DB::commit();

            Log::info('Case result deleted', [
                'case_result_id' => $id,
                'deleted_by' => $request->user_id ?? 1
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Case result deleted successfully'
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete case result', [
                'case_result_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete case result',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PATCH /api/case-results/{id}/deactivate
     * Deactivate case result
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        try {
            $caseResult = TblCcCaseResult::whereNull('deletedAt')->find($id);

            if (!$caseResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case result not found'
                ], 404);
            }

            DB::beginTransaction();

            $caseResult->update([
                'caseResultActive' => false,
                'deactivatedAt' => now(),
                'deactivatedBy' => $request->user_id ?? 1,
                'updatedAt' => now(),
                'updatedBy' => $request->user_id ?? 1
            ]);

            DB::commit();

            Log::info('Case result deactivated', [
                'case_result_id' => $id,
                'deactivated_by' => $request->user_id ?? 1
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Case result deactivated successfully',
                'data' => $caseResult
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to deactivate case result', [
                'case_result_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate case result',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PATCH /api/case-results/{id}/activate
     * Activate case result
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        try {
            $caseResult = TblCcCaseResult::whereNull('deletedAt')->find($id);

            if (!$caseResult) {
                return response()->json([
                    'success' => false,
                    'message' => 'Case result not found'
                ], 404);
            }

            DB::beginTransaction();

            $caseResult->update([
                'caseResultActive' => true,
                'deactivatedAt' => null,
                'deactivatedBy' => null,
                'updatedAt' => now(),
                'updatedBy' => $request->user_id ?? 1
            ]);

            DB::commit();

            Log::info('Case result activated', [
                'case_result_id' => $id,
                'activated_by' => $request->user_id ?? 1
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Case result activated successfully',
                'data' => $caseResult
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Failed to activate case result', [
                'case_result_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to activate case result',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
