<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TblCcScript;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class TblCcScriptController extends Controller
{
    /**
     * Display a listing of all active scripts
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            // Get all active scripts, sorted by source, segment, then receiver
            $scripts = TblCcScript::where('scriptActive', true)
                                 ->orderBy('source')
                                 ->orderBy('segment')
                                 ->orderBy('receiver')
                                 ->orderBy('daysPastDueFrom')
                                 ->get();

            return response()->json([
                'success' => true,
                'message' => 'Scripts retrieved successfully',
                'data' => $scripts,
                'total' => $scripts->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve scripts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve scripts',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified script by ID
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $script = TblCcScript::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Script retrieved successfully',
                'data' => $script
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve script', [
                'scriptId' => $id,
                'error' => $e->getMessage()
            ]);

            $statusCode = $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500;

            return response()->json([
                'success' => false,
                'message' => $statusCode === 404 ? 'Script not found' : 'Failed to retrieve script',
                'error' => $statusCode === 404 ? 'The requested script does not exist' : 'Internal server error'
            ], $statusCode);
        }
    }

    /**
     * Get scripts filtered by source
     *
     * @param string $source
     * @return JsonResponse
     */
    public function getBySource(string $source): JsonResponse
    {
        try {
            // Validate source
            if (!in_array($source, TblCcScript::getSources())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid source',
                    'error' => 'Source must be one of: ' . implode(', ', TblCcScript::getSources())
                ], 400);
            }

            $scripts = TblCcScript::where('scriptActive', true)
                                 ->where('source', $source)
                                 ->orderBy('segment')
                                 ->orderBy('receiver')
                                 ->orderBy('daysPastDueFrom')
                                 ->get();

            return response()->json([
                'success' => true,
                'message' => "Scripts for source '{$source}' retrieved successfully",
                'data' => $scripts,
                'source' => $source,
                'total' => $scripts->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get scripts by source', [
                'source' => $source,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve scripts by source',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get scripts filtered by segment
     *
     * @param string $segment
     * @return JsonResponse
     */
    public function getBySegment(string $segment): JsonResponse
    {
        try {
            // Validate segment
            if (!in_array($segment, TblCcScript::getSegments())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid segment',
                    'error' => 'Segment must be one of: ' . implode(', ', TblCcScript::getSegments())
                ], 400);
            }

            $scripts = TblCcScript::where('scriptActive', true)
                                 ->where('segment', $segment)
                                 ->orderBy('source')
                                 ->orderBy('receiver')
                                 ->orderBy('daysPastDueFrom')
                                 ->get();

            return response()->json([
                'success' => true,
                'message' => "Scripts for segment '{$segment}' retrieved successfully",
                'data' => $scripts,
                'segment' => $segment,
                'total' => $scripts->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get scripts by segment', [
                'segment' => $segment,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve scripts by segment',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get scripts filtered by receiver
     *
     * @param string $receiver
     * @return JsonResponse
     */
    public function getByReceiver(string $receiver): JsonResponse
    {
        try {
            // Validate receiver
            if (!in_array($receiver, TblCcScript::getReceivers())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid receiver',
                    'error' => 'Receiver must be one of: ' . implode(', ', TblCcScript::getReceivers())
                ], 400);
            }

            $scripts = TblCcScript::where('scriptActive', true)
                                 ->where('receiver', $receiver)
                                 ->orderBy('source')
                                 ->orderBy('segment')
                                 ->orderBy('daysPastDueFrom')
                                 ->get();

            return response()->json([
                'success' => true,
                'message' => "Scripts for receiver '{$receiver}' retrieved successfully",
                'data' => $scripts,
                'receiver' => $receiver,
                'total' => $scripts->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get scripts by receiver', [
                'receiver' => $receiver,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve scripts by receiver',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get scripts grouped by combinations
     *
     * @return JsonResponse
     */
    public function getGrouped(): JsonResponse
    {
        try {
            $scripts = TblCcScript::where('scriptActive', true)
                                 ->orderBy('source')
                                 ->orderBy('segment')
                                 ->orderBy('receiver')
                                 ->orderBy('daysPastDueFrom')
                                 ->get()
                                 ->groupBy(['source', 'segment', 'receiver']);

            return response()->json([
                'success' => true,
                'message' => 'Scripts grouped by source, segment, and receiver retrieved successfully',
                'data' => $scripts,
                'summary' => [
                    'total_scripts' => $scripts->flatten(3)->count(),
                    'sources' => $scripts->keys(),
                    'structure' => 'source -> segment -> receiver -> scripts[]'
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get grouped scripts', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grouped scripts',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get metadata (available options)
     *
     * @return JsonResponse
     */
    public function getMetadata(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Script metadata retrieved successfully',
                'data' => [
                    'sources' => TblCcScript::getSources(),
                    'segments' => TblCcScript::getSegments(),
                    'receivers' => TblCcScript::getReceivers(),
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get script metadata', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve metadata',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all scripts (including inactive)
     *
     * @return JsonResponse
     */
    public function all(): JsonResponse
    {
        try {
            $scripts = TblCcScript::orderBy('source')
                                 ->orderBy('segment')
                                 ->orderBy('receiver')
                                 ->orderBy('daysPastDueFrom')
                                 ->get();

            $active = $scripts->where('scriptActive', true);
            $inactive = $scripts->where('scriptActive', false);

            return response()->json([
                'success' => true,
                'message' => 'All scripts retrieved successfully',
                'data' => $scripts,
                'summary' => [
                    'total' => $scripts->count(),
                    'active' => $active->count(),
                    'inactive' => $inactive->count(),
                    'by_source' => $scripts->groupBy('source')->map->count(),
                    'by_segment' => $scripts->groupBy('segment')->map->count(),
                    'by_receiver' => $scripts->groupBy('receiver')->map->count()
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to retrieve all scripts', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve all scripts',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get simple statistics
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        try {
            $total = TblCcScript::count();
            $active = TblCcScript::where('scriptActive', true)->count();
            $inactive = TblCcScript::where('scriptActive', false)->count();
            $deactivated = TblCcScript::whereNotNull('dtDeactivated')->count();

            $bySource = TblCcScript::select('source')
                                  ->selectRaw('COUNT(*) as count')
                                  ->selectRaw('SUM(CASE WHEN scriptActive = true THEN 1 ELSE 0 END) as active_count')
                                  ->groupBy('source')
                                  ->orderBy('source')
                                  ->get();

            $bySegment = TblCcScript::select('segment')
                                   ->selectRaw('COUNT(*) as count')
                                   ->selectRaw('SUM(CASE WHEN scriptActive = true THEN 1 ELSE 0 END) as active_count')
                                   ->groupBy('segment')
                                   ->orderBy('segment')
                                   ->get();

            $byReceiver = TblCcScript::select('receiver')
                                    ->selectRaw('COUNT(*) as count')
                                    ->selectRaw('SUM(CASE WHEN scriptActive = true THEN 1 ELSE 0 END) as active_count')
                                    ->groupBy('receiver')
                                    ->orderBy('receiver')
                                    ->get();

            return response()->json([
                'success' => true,
                'message' => 'Statistics retrieved successfully',
                'data' => [
                    'total_scripts' => $total,
                    'active_scripts' => $active,
                    'inactive_scripts' => $inactive,
                    'deactivated_scripts' => $deactivated,
                    'by_source' => $bySource,
                    'by_segment' => $bySegment,
                    'by_receiver' => $byReceiver,
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get script statistics', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
