<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DutyRosterController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned the "api" middleware group. Make something great!
|
*/

// Public Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
});

// Duty Roster Management Routes
Route::prefix('duty-rosters')->group(function () {
    // Get duty roster for date range
    Route::get('/', [DutyRosterController::class, 'getDutyRoster']);

    // Create duty roster
    Route::post('/', [DutyRosterController::class, 'createDutyRoster']);

    // Delete agent from duty roster by user_id and date
    Route::delete('/', [DutyRosterController::class, 'deleteFromDutyRoster']);

    // Get available agents
    Route::get('/agents/available', [DutyRosterController::class, 'getAvailableAgents']);

    // Get agents for specific date (helper for call assignment later)
    Route::get('/agents/{date}', [DutyRosterController::class, 'getAgentsForDate']);
});

// Calls Management Routes
Route::prefix('calls')->group(function () {
    // Get all calls
    Route::get('/', [App\Http\Controllers\API\CallController::class, 'index']);

    // Get assigned calls for specific user
    Route::get('/user/{userId}', [App\Http\Controllers\API\CallController::class, 'getAssignedCallsForUser']);
});

// Call Assignment Routes
Route::prefix('call-assignments')->group(function () {
    // Assign calls to available agents
    Route::post('/assign', [App\Http\Controllers\API\CallAssignmentController::class, 'assignCalls']);
});

// // Protected Routes (TODO: Add JWT middleware)
// Route::middleware('auth:sanctum')->group(function () {
//     Route::prefix('auth')->group(function () {
//         Route::get('me', [AuthController::class, 'me']);
//         Route::post('logout', [AuthController::class, 'logout']);
//     });

//     // Future protected routes
//     // Route::apiResource('users', UserController::class);
//     // Route::apiResource('duty-rosters', DutyRosterController::class);
//     // Route::apiResource('call-assignments', CallAssignmentController::class);
// });

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });
