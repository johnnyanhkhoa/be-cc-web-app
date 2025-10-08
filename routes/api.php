<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DutyRosterController;
use App\Http\Controllers\API\PMTGuidelineController;
use App\Http\Controllers\API\TblCcPhoneCollectionController;
use App\Http\Controllers\API\TblCcPhoneCollectionDetailController;
use App\Http\Controllers\API\TblCcReasonController;
use App\Http\Controllers\API\TblCcRemarkController;
use App\Http\Controllers\API\TblCcScriptController;
use App\Http\Controllers\API\UserController;

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
    Route::post('check-allow', [AuthController::class, 'checkAllow']);

    // NEW: Check user roles and permissions by team
    Route::post('check-role', [AuthController::class, 'checkRole']);
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

// Contract Information Routes
Route::prefix('contracts')->group(function () {
    // Get contract details by phoneCollectionId (main endpoint)
    // /api/contracts/1234 where 1234 is phoneCollectionId
    Route::get('/{phoneCollectionId}', [App\Http\Controllers\API\ContractController::class, 'getContractDetails']);

    // Get contract details directly by contractId (optional)
    // /api/contracts/direct/495347 where 495347 is contractId
    Route::get('/direct/{contractId}', [App\Http\Controllers\API\ContractController::class, 'getContractDetailsByContractId']);

    // Get phone collection info with contract details combined
    // /api/contracts/1234/full where 1234 is phoneCollectionId
    Route::get('/{phoneCollectionId}/full', [App\Http\Controllers\API\ContractController::class, 'getPhoneCollectionWithContract']);
});

// Call Assignment Routes
Route::prefix('call-assignments')->group(function () {
    // Assign calls to available agents
    Route::post('/assign', [App\Http\Controllers\API\CallAssignmentController::class, 'assignCalls']);
});

// User Management Routes
Route::prefix('users')->group(function () {
    Route::get('/available-for-assign', [UserController::class, 'getAvailableUsersForAssign']);
});

Route::get('/users/available-for-assign/debug', [App\Http\Controllers\API\UserController::class, 'debugAvailableUsers']);

// Phone Collection API Routes
Route::prefix('cc-phone-collections')->group(function () {
    // Get phone collection records with filtering by status and assignedTo
    Route::get('/', [TblCcPhoneCollectionController::class, 'index']);

    // Mark phone collection as completed
    Route::patch('/{phoneCollectionId}/complete', [TblCcPhoneCollectionController::class, 'markAsCompleted']);

    // NEW: Manual assign phone collections to user
    Route::post('/manual-assign', [TblCcPhoneCollectionController::class, 'manualAssign']);
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

// Script API Routes
Route::prefix('scripts')->group(function () {
    // Get scripts based on batchId and daysPastDue
    Route::post('/', [TblCcScriptController::class, 'getScripts']);

    // Get scripts by phoneCollectionId (NEW)
    Route::get('/phone-collection/{phoneCollectionId}', [TblCcScriptController::class, 'getScriptsByPhoneCollection']);

    // Get all active batches with script collections
    Route::get('/batches', [TblCcScriptController::class, 'getBatchesWithScripts']);

    // Get script details by scriptId
    Route::get('/{scriptId}', [TblCcScriptController::class, 'getScriptById']);
});

// TblCcPhoneCollectionDetail API Routes
Route::prefix('cc-phone-collection-details')->group(function () {
    // Create new phone collection detail (now requires phoneCollectionId)
    Route::post('/', [TblCcPhoneCollectionDetailController::class, 'store']);

    // Get supporting data for forms
    Route::get('/case-results', [TblCcPhoneCollectionDetailController::class, 'getCaseResults']);
    Route::get('/standard-remarks', [TblCcPhoneCollectionDetailController::class, 'getStandardRemarks']);
    Route::get('/metadata', [TblCcPhoneCollectionDetailController::class, 'getMetadata']);

    // Get recent records for reference
    Route::get('/recent', [TblCcPhoneCollectionDetailController::class, 'getRecent']);
});

Route::get('/cc-phone-collection-details/contract/{contractId}/remarks', [App\Http\Controllers\API\TblCcPhoneCollectionDetailController::class, 'getRemarksByContract']);

// Image Upload API Routes
Route::prefix('images')->group(function () {
    // Upload images to Google Drive
    Route::post('/upload', [App\Http\Controllers\API\ImageUploadController::class, 'uploadImages']);

    // Callback from Google Image API (IMPORTANT: Must be public)
    Route::post('/upload-callback', [App\Http\Controllers\API\ImageUploadController::class, 'uploadCallback']);

    // Get images by IDs
    Route::post('/by-ids', [App\Http\Controllers\API\ImageUploadController::class, 'getImagesByIds']);
});

// Call Attempts API Routes (NEW)
Route::prefix('attempts')->group(function () {
    // Get all call attempts for a specific phone collection
    Route::get('/{phoneCollectionId}', [TblCcPhoneCollectionDetailController::class, 'getCallAttempts']);
});

// Payment Guideline API Routes
Route::prefix('payment-guidelines')->group(function () {
    // Get payment guideline by name (main endpoint)
    Route::post('/by-name', [PMTGuidelineController::class, 'getByName']);

    // Get all payment guidelines
    Route::get('/', [PMTGuidelineController::class, 'getAll']);

    // Search payment guidelines
    Route::get('/search', [PMTGuidelineController::class, 'search']);

    // Get payment guideline by ID
    Route::get('/{pmtId}', [PMTGuidelineController::class, 'getById']);
});

// Voice Call (Asterisk Integration) Routes
Route::prefix('voice-call')->group(function () {
    // Initiate voice call
    Route::post('/initiate', [App\Http\Controllers\API\VoiceCallController::class, 'initiateCall']);

    // Get call status (optional)
    Route::get('/status/{callId}', [App\Http\Controllers\API\VoiceCallController::class, 'getCallStatus']);

    // NEW: Get call logs
    Route::get('/logs', [App\Http\Controllers\API\VoiceCallController::class, 'getCallLogs']);
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
