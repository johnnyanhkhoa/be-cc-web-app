<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    protected AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    public function getAnalyticsSummary(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from'
        ]);

        $data = $this->analyticsService->getAnalyticsSummary(
            $request->from,
            $request->to
        );

        return response()->json([
            'success' => true,
            'message' => 'Analytics data retrieved successfully',
            'data' => $data,
            'dateRange' => [
                'from' => $request->from,
                'to' => $request->to
            ]
        ]);
    }
}
