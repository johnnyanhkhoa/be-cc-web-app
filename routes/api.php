<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DutyRosterController;
use App\Http\Controllers\API\TblCcPhoneCollectionController;
use App\Http\Controllers\API\TblCcPhoneCollectionDetailController;
use App\Http\Controllers\API\TblCcReasonController;
use App\Http\Controllers\API\TblCcRemarkController;
use App\Http\Controllers\API\TblCcScriptController;

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

// Phone Collection Sync Routes
Route::prefix('sync')->group(function () {
    Route::post('phone-collections', [App\Http\Controllers\API\PhoneCollectionSyncController::class, 'syncPhoneCollections']);
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

// TblCcPhoneCollection API Routes
Route::prefix('cc-phone-collections')->group(function () {
    // Get phone collection records with filtering by status and assignedTo
    Route::get('/', [TblCcPhoneCollectionController::class, 'index']);

    // Create single phone collection record
    Route::post('/', [TblCcPhoneCollectionController::class, 'store']);

    // Bulk create multiple phone collection records
    Route::post('/collection', [TblCcPhoneCollectionController::class, 'bulkStore']);
});

Route::prefix('cc-reasons')->group(function () {
    // Get all active reasons (no pagination, no filters)
    Route::get('/', [TblCcReasonController::class, 'index']);

    // Get specific reason by ID
    Route::get('/{id}', [TblCcReasonController::class, 'show']);

    // Get reasons grouped by type
    Route::get('/group/by-type', [TblCcReasonController::class, 'getByType']);

    // Get all reasons (including inactive)
    Route::get('/all/records', [TblCcReasonController::class, 'all']);

    // Get simple statistics
    Route::get('/stats/summary', [TblCcReasonController::class, 'stats']);
});

// TblCcRemark API Routes (Read Only - Simple)
Route::prefix('cc-remarks')->group(function () {
    // Get all active remarks
    Route::get('/', [TblCcRemarkController::class, 'index']);

    // Get specific remark by ID
    Route::get('/{id}', [TblCcRemarkController::class, 'show']);

    // Get remarks grouped by contact type
    Route::get('/group/by-contact-type', [TblCcRemarkController::class, 'getByContactType']);

    // Get remarks for specific contact type (all/rpc/tpc)
    Route::get('/contact-type/{contactType}', [TblCcRemarkController::class, 'getByType']);

    // Get all contact types with statistics
    Route::get('/meta/contact-types', [TblCcRemarkController::class, 'getContactTypes']);

    // Get all remarks (including inactive)
    Route::get('/all/records', [TblCcRemarkController::class, 'all']);

    // Get simple statistics
    Route::get('/stats/summary', [TblCcRemarkController::class, 'stats']);
});

// TblCcScript API Routes (Read Only - Simple)
Route::prefix('cc-scripts')->group(function () {
    // Get all active scripts
    Route::get('/', [TblCcScriptController::class, 'index']);

    // Get specific script by ID
    Route::get('/{id}', [TblCcScriptController::class, 'show']);

    // Get scripts by source (normal/dslp)
    Route::get('/source/{source}', [TblCcScriptController::class, 'getBySource']);

    // Get scripts by segment (pre-due/past-due)
    Route::get('/segment/{segment}', [TblCcScriptController::class, 'getBySegment']);

    // Get scripts by receiver (rpc/tpc)
    Route::get('/receiver/{receiver}', [TblCcScriptController::class, 'getByReceiver']);

    // Get scripts grouped by combinations
    Route::get('/group/all', [TblCcScriptController::class, 'getGrouped']);

    // Get metadata (available options)
    Route::get('/meta/options', [TblCcScriptController::class, 'getMetadata']);

    // Get all scripts (including inactive)
    Route::get('/all/records', [TblCcScriptController::class, 'all']);

    // Get simple statistics
    Route::get('/stats/summary', [TblCcScriptController::class, 'stats']);
});

// TblCcPhoneCollectionDetail API Routes
Route::prefix('cc-phone-collection-details')->group(function () {
    // Create new phone collection detail
    Route::post('/', [TblCcPhoneCollectionDetailController::class, 'store']);

    // Get supporting data for forms
    Route::get('/case-results', [TblCcPhoneCollectionDetailController::class, 'getCaseResults']);
    Route::get('/standard-remarks', [TblCcPhoneCollectionDetailController::class, 'getStandardRemarks']);
    Route::get('/metadata', [TblCcPhoneCollectionDetailController::class, 'getMetadata']);

    // Get recent records for reference
    Route::get('/recent', [TblCcPhoneCollectionDetailController::class, 'getRecent']);
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
