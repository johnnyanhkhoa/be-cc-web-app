<?php

namespace App\Services;

use App\Models\TblCcUploadImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Exception;

class ImageUploadService
{
    private const GOOGLE_IMAGE_API_URL = 'https://google-image.mmapp.xyz/api/v2/upload-image';
    private const BEARER_TOKEN = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.payload.u8N4a0w3b9XyZkLmPqRsT7V2hG';
    private const DRIVE_NAME = 'Call-Collection';
    private const MODULE_NAME = 'phone_collection';
    private const FOLDER = 'uploads/documents';

    /**
     * Upload image to Google Drive via external API
     *
     * @param UploadedFile $file
     * @param int $userId
     * @param string $username
     * @param string|null $description
     * @return TblCcUploadImage
     * @throws Exception
     */
    public function uploadImageToGoogleDrive(
        UploadedFile $file,
        int $userId,
        string $username,
        ?string $description = null
    ): TblCcUploadImage {
        try {
            // Generate callback URL
            $callbackUrl = 'http://cc-staging-be.mmapp.xyz/api/images/upload-callback';

            Log::info('Uploading image to Google Drive', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'user_id' => $userId,
                'callback_url' => $callbackUrl
            ]);

            // Prepare form data
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . self::BEARER_TOKEN,
                ])
                ->attach('upload_file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post(self::GOOGLE_IMAGE_API_URL, [
                    'drive_name' => self::DRIVE_NAME,
                    'username' => $username,
                    'user_id' => (string)$userId,
                    'module_name' => self::MODULE_NAME,
                    'folder' => self::FOLDER,
                    'description' => $description ?? 'Phone collection image upload',
                    'resize' => 'false',
                    'call_back_url' => $callbackUrl,
                ]);

            Log::info('Google Image API response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if (!$response->successful()) {
                throw new Exception('Failed to upload to Google Image API: ' . $response->body(), $response->status());
            }

            $responseData = $response->json();

            if (!isset($responseData['status_code']) || $responseData['status_code'] != 1) {
                throw new Exception('Google Image API returned error: ' . ($responseData['status_message'] ?? 'Unknown error'));
            }

            $data = $responseData['data'];

            // Save to database with local URL
            $uploadImage = TblCcUploadImage::create([
                'fileName' => $data['filename'],
                'fileType' => $file->getClientOriginalExtension(),
                'localUrl' => $data['url'], // Local URL from Google Image API
                'googleUrl' => null, // Will be updated via callback
                'logId' => (string)$data['log_id'],
                'createdBy' => $userId,
                'createdAt' => now(),
            ]);

            Log::info('Image upload record created', [
                'upload_image_id' => $uploadImage->uploadImageId,
                'log_id' => $uploadImage->logId,
                'filename' => $uploadImage->fileName,
                'local_url' => $uploadImage->localUrl,
                'log_id' => $data['log_id'] ?? null
            ]);

            return $uploadImage;

        } catch (Exception $e) {
            Log::error('Failed to upload image to Google Drive', [
                'filename' => $file->getClientOriginalName(),
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Update image with Google Drive URL from callback
     *
     * @param int $logId
     * @param string $googleUrl
     * @param string $fileId
     * @return TblCcUploadImage|null
     */
    public function updateGoogleUrl(int $logId, string $googleUrl, string $fileId): ?TblCcUploadImage
    {
        try {
            // Find by logId - CHÍNH XÁC HƠN
            $uploadImage = TblCcUploadImage::where('logId', (string)$logId)->first();

            if (!$uploadImage) {
                Log::warning('Upload image not found for callback', [
                    'log_id' => $logId,
                    'google_url' => $googleUrl
                ]);
                return null;
            }

            $uploadImage->update([
                'googleUrl' => $googleUrl,
                'updatedAt' => now(),
            ]);

            Log::info('Google URL updated successfully', [
                'upload_image_id' => $uploadImage->uploadImageId,
                'log_id' => $logId,
                'google_url' => $googleUrl,
                'file_id' => $fileId
            ]);

            return $uploadImage;

        } catch (Exception $e) {
            Log::error('Failed to update Google URL', [
                'log_id' => $logId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get images by IDs
     *
     * @param array $imageIds
     * @return array
     */
    public function getImagesByIds(array $imageIds): array
    {
        $images = TblCcUploadImage::whereIn('uploadImageId', $imageIds)->get();

        return $images->map(function ($image) {
            return [
                'uploadImageId' => $image->uploadImageId,
                'fileName' => $image->fileName,
                'fileType' => $image->fileType,
                'localUrl' => $image->localUrl,
                'googleUrl' => $image->googleUrl,
                'createdAt' => $image->createdAt?->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }
}
