<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\TblCcUploadImage;

class ImageUploadController extends Controller
{
    protected $imageUploadService;

    public function __construct(ImageUploadService $imageUploadService)
    {
        $this->imageUploadService = $imageUploadService;
    }

    /**
     * Upload images to Google Drive
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadImages(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'images' => ['required', 'array', 'min:1', 'max:10'],
                'images.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240'], // 10MB max
                'userId' => ['required', 'integer'],
                'username' => ['required', 'string'],
                'description' => ['nullable', 'string'],
                'phoneCollectionDetailId' => ['required', 'integer', 'exists:tbl_CcPhoneCollectionDetail,phoneCollectionDetailId'], // ✅ THÊM
            ]);

            $files = $request->file('images');
            $userId = $request->input('userId');
            $username = $request->input('username');
            $description = $request->input('description');
            $phoneCollectionDetailId = $request->input('phoneCollectionDetailId');

            Log::info('Starting image upload to Google Drive', [
                'file_count' => count($files),
                'user_id' => $userId,
                'username' => $username,
                'phone_collection_detail_id' => $phoneCollectionDetailId // ✅ THÊM
            ]);

            $uploadedImages = [];

            foreach ($files as $file) {
                $uploadImage = $this->imageUploadService->uploadImageToGoogleDrive(
                    $file,
                    $userId,
                    $username,
                    $description,
                    $phoneCollectionDetailId // ✅ THÊM PARAMETER
                );

                $uploadedImages[] = [
                    'uploadImageId' => $uploadImage->uploadImageId,
                    'fileName' => $uploadImage->fileName,
                    'fileType' => $uploadImage->fileType,
                    'localUrl' => $uploadImage->localUrl,
                    'googleUrl' => $uploadImage->googleUrl, // null initially
                    'status' => 'processing', // Indicate Google Drive upload is in progress
                    'createdAt' => $uploadImage->createdAt?->format('Y-m-d H:i:s'),
                ];
            }

            Log::info('Images uploaded successfully', [
                'uploaded_count' => count($uploadedImages),
                'image_ids' => array_column($uploadedImages, 'uploadImageId')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Images uploaded to local storage and queued for Google Drive',
                'data' => [
                    'images' => $uploadedImages,
                    'uploadImageIds' => array_column($uploadedImages, 'uploadImageId'),
                    'totalUploaded' => count($uploadedImages),
                    'note' => 'Google Drive URLs will be available shortly via callback'
                ]
            ], 201);

        } catch (Exception $e) {
            Log::error('Failed to upload images', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Callback endpoint for Google Image API
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadCallback(Request $request): JsonResponse
    {
        try {
            Log::info('=== CALLBACK RECEIVED ===', [
                'full_request' => $request->all(),
                'headers' => $request->headers->all(),
            ]);

            $statusCode = $request->input('status_code');
            $data = $request->input('data');
            $logId = $data['log_id'] ?? null;

            Log::info('Processing callback', [
                'status_code' => $statusCode,
                'log_id' => $logId,
                'google_url' => $data['url'] ?? null,
                'file_id' => $data['fileId'] ?? null,
            ]);

            if ($statusCode != 1) {
                Log::error('Callback indicated failure', [
                    'status_message' => $request->input('status_message'),
                ]);
                return response()->json(['success' => false], 200);
            }

            if (!$logId) {
                Log::error('Missing log_id in callback', ['data' => $data]);
                return response()->json(['success' => false], 200);
            }

            // Check if record exists BEFORE update
            $existing = TblCcUploadImage::where('logId', (string)$logId)->first();

            Log::info('Looking for upload record', [
                'log_id' => $logId,
                'found' => $existing ? 'YES' : 'NO',
                'record' => $existing ? $existing->toArray() : null
            ]);

            if (!$existing) {
                Log::error('Record not found for log_id', [
                    'log_id' => $logId,
                    'all_records' => TblCcUploadImage::whereNull('googleUrl')->get()->toArray()
                ]);
                return response()->json(['success' => false], 200);
            }

            // Update
            $uploadImage = $this->imageUploadService->updateGoogleUrl(
                $logId,
                $data['url'],
                $data['fileId']
            );

            Log::info('=== CALLBACK PROCESSED ===', [
                'success' => true,
                'upload_image_id' => $uploadImage->uploadImageId ?? null,
            ]);

            return response()->json(['success' => true], 200);

        } catch (Exception $e) {
            Log::error('=== CALLBACK ERROR ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['success' => false], 200);
        }
    }

    /**
     * Get images by IDs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getImagesByIds(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'imageIds' => ['required', 'array'],
                'imageIds.*' => ['integer'],
            ]);

            $imageIds = $request->input('imageIds');
            $images = $this->imageUploadService->getImagesByIds($imageIds);

            return response()->json([
                'success' => true,
                'message' => 'Images retrieved successfully',
                'data' => $images
            ], 200);

        } catch (Exception $e) {
            Log::error('Failed to get images by IDs', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve images',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
