<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class ImageUploadController extends Controller
{
    protected $imageUploadService;

    public function __construct(ImageUploadService $imageUploadService)
    {
        $this->imageUploadService = $imageUploadService;
    }

    /**
     * Upload multiple images
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadImages(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'images' => ['required', 'array', 'min:1', 'max:10'],
                'images.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'], // 5MB max
                'createdBy' => ['required', 'integer'],
            ]);

            $files = $request->file('images');
            $createdBy = $request->input('createdBy');

            Log::info('Starting image upload', [
                'file_count' => count($files),
                'created_by' => $createdBy
            ]);

            // Upload images using service
            $uploadedImages = $this->imageUploadService->uploadImages($files, $createdBy);

            // Transform response
            $responseData = collect($uploadedImages)->map(function ($image) {
                return [
                    'uploadImageId' => $image->uploadImageId,
                    'fileName' => $image->fileName,
                    'localUrl' => $image->localUrl,
                    'fullLocalUrl' => $image->getFullLocalUrl(),
                    'googleUrl' => $image->googleUrl,
                    'fileType' => $image->fileType,
                    'createdAt' => $image->createdAt?->format('Y-m-d H:i:s'),
                ];
            });

            Log::info('Images uploaded successfully', [
                'uploaded_count' => count($uploadedImages),
                'image_ids' => $responseData->pluck('uploadImageId')->toArray()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Images uploaded successfully',
                'data' => [
                    'images' => $responseData,
                    'uploadImageIds' => $responseData->pluck('uploadImageId')->toArray(),
                    'totalUploaded' => count($uploadedImages)
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
     * Get image details by IDs
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
