<?php

namespace App\Services;

use App\Models\TblCcUploadImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class ImageUploadService
{
    /**
     * Upload images and save to database
     *
     * @param array $files
     * @param int $createdBy
     * @return array
     */
    public function uploadImages(array $files, int $createdBy): array
    {
        $uploadedImages = [];

        foreach ($files as $file) {
            try {
                $uploadedImage = $this->uploadSingleImage($file, $createdBy);
                $uploadedImages[] = $uploadedImage;
            } catch (Exception $e) {
                Log::error('Failed to upload single image', [
                    'error' => $e->getMessage(),
                    'file_name' => $file->getClientOriginalName()
                ]);
                throw $e;
            }
        }

        return $uploadedImages;
    }

    /**
     * Upload single image
     *
     * @param UploadedFile $file
     * @param int $createdBy
     * @return TblCcUploadImage
     */
    public function uploadSingleImage(UploadedFile $file, int $createdBy): TblCcUploadImage
    {
        // Validate file
        $this->validateImage($file);

        // Generate unique filename
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileName = time() . '_' . Str::random(10) . '.' . $extension;

        // Upload to storage/app/public/cc-images
        $path = $file->storeAs('cc-images', $fileName, 'public');

        // Create local URL
        $localUrl = '/storage/' . $path;

        // Save to database
        $uploadImage = TblCcUploadImage::create([
            'fileName' => $originalName,
            'fileType' => $file->getMimeType(),
            'localUrl' => $localUrl,
            'googleUrl' => null, // Để trống như yêu cầu
            'createdBy' => $createdBy,
        ]);

        Log::info('Image uploaded successfully', [
            'upload_image_id' => $uploadImage->uploadImageId,
            'file_name' => $originalName,
            'local_url' => $localUrl
        ]);

        return $uploadImage;
    }

    /**
     * Validate uploaded image
     *
     * @param UploadedFile $file
     * @throws Exception
     */
    private function validateImage(UploadedFile $file): void
    {
        // Check file size (max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new Exception('File size must not exceed 5MB');
        }

        // Check file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            throw new Exception('File must be an image (JPEG, PNG, GIF, WEBP)');
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
        if (empty($imageIds)) {
            return [];
        }

        return TblCcUploadImage::whereIn('uploadImageId', $imageIds)
            ->get()
            ->map(function ($image) {
                return [
                    'uploadImageId' => $image->uploadImageId,
                    'fileName' => $image->fileName,
                    'fileType' => $image->fileType,
                    'localUrl' => $image->localUrl,
                    'fullLocalUrl' => $image->getFullLocalUrl(),
                    'googleUrl' => $image->googleUrl,
                    'createdAt' => $image->createdAt?->format('Y-m-d H:i:s'),
                ];
            })
            ->toArray();
    }
}
