<?php

namespace App\Services;

use App\Models\VisaApplication;
use App\Models\VisaApplicantFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class DirectFileUploadService
{
    private S3Client $s3Client;
    private string $bucket;
    private array $uploadSessions = [];

    public function __construct()
    {
        $config = config('filesystems.disks.minio');
        
        $this->bucket = $config['bucket'];
        
        // Use MINIO_URL for browser-accessible pre-signed URLs
        // MINIO_URL should be http://localhost:9000 (browser-accessible)
        // MINIO_ENDPOINT would be http://minio:9000 (Docker internal)
        $endpoint = $config['url'] ?? $config['endpoint'];
        
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => $config['region'] ?? 'us-east-1',
            'endpoint' => $endpoint,
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ]);
    }

    /**
     * Initiate a multipart upload and generate pre-signed URLs for each part.
     */
    public function initiateMultipartUpload(
        VisaApplication $visaApplication,
        int $fileCategoryId,
        string $fileName,
        int $fileSize,
        string $mimeType,
        int $totalParts
    ): array {
        // Generate unique key for the file
        $key = $this->generateFileKey($visaApplication->id, $fileName);

        // Initiate multipart upload
        $result = $this->s3Client->createMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'ContentType' => $mimeType,
            'Metadata' => [
                'visa_application_id' => (string) $visaApplication->id,
                'file_category_id' => (string) $fileCategoryId,
                'original_name' => $fileName,
            ],
        ]);

        $uploadId = $result['UploadId'];

        // Generate pre-signed URLs for each part
        $presignedUrls = [];
        $expiresIn = '+1 hour'; // URLs valid for 1 hour

        for ($partNumber = 1; $partNumber <= $totalParts; $partNumber++) {
            $cmd = $this->s3Client->getCommand('UploadPart', [
                'Bucket' => $this->bucket,
                'Key' => $key,
                'UploadId' => $uploadId,
                'PartNumber' => $partNumber,
            ]);

            $presignedRequest = $this->s3Client->createPresignedRequest($cmd, $expiresIn);
            $presignedUrls[] = [
                'part_number' => $partNumber,
                'url' => (string) $presignedRequest->getUri(),
            ];
        }

        // Store upload session metadata (optional, for tracking)
        $this->uploadSessions[$uploadId] = [
            'key' => $key,
            'visa_application_id' => $visaApplication->id,
            'file_category_id' => $fileCategoryId,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'total_parts' => $totalParts,
            'initiated_at' => now()->toIso8601String(),
        ];

        return [
            'upload_id' => $uploadId,
            'key' => $key,
            'presigned_urls' => $presignedUrls,
            'expires_in' => 3600, // seconds
        ];
    }

    /**
     * Complete a multipart upload by assembling all parts.
     */
    public function completeMultipartUpload(
        VisaApplication $visaApplication,
        string $uploadId,
        array $parts
    ): VisaApplicantFile {
        // Retrieve session metadata
        $session = $this->uploadSessions[$uploadId] ?? null;
        
        if (!$session) {
            throw new \Exception('Upload session not found. Upload may have expired.');
        }

        // Sort parts by part number
        usort($parts, fn($a, $b) => $a['part_number'] <=> $b['part_number']);

        // Format parts for S3 API
        $formattedParts = array_map(fn($part) => [
            'PartNumber' => $part['part_number'],
            'ETag' => $part['etag'],
        ], $parts);

        try {
            // Complete the multipart upload in S3
            $result = $this->s3Client->completeMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $session['key'],
                'UploadId' => $uploadId,
                'MultipartUpload' => [
                    'Parts' => $formattedParts,
                ],
            ]);

            // Create database record
            $file = VisaApplicantFile::create([
                'visa_application_id' => $visaApplication->id,
                'applicant_id' => $visaApplication->applicant_id,
                'file_category_id' => $session['file_category_id'],
                'original_name' => $session['file_name'],
                'stored_name' => basename($session['key']),
                'path' => $session['key'],
                'size_bytes' => $session['file_size'],
                'mime_type' => $session['mime_type'],
                'disk' => 'minio',
            ]);

            // Clean up session
            unset($this->uploadSessions[$uploadId]);

            // TODO: Broadcast success event when VisaApplicantFileUploaded event is created
            // broadcast(new \App\Events\VisaApplicantFileUploaded($file))->toOthers();

            return $file;

        } catch (AwsException $e) {
            throw new \Exception('Failed to complete multipart upload: ' . $e->getMessage());
        }
    }

    /**
     * Abort a multipart upload and clean up partial data.
     */
    public function abortMultipartUpload(string $uploadId): void
    {
        $session = $this->uploadSessions[$uploadId] ?? null;

        if (!$session) {
            throw new \Exception('Upload session not found.');
        }

        try {
            $this->s3Client->abortMultipartUpload([
                'Bucket' => $this->bucket,
                'Key' => $session['key'],
                'UploadId' => $uploadId,
            ]);

            // Clean up session
            unset($this->uploadSessions[$uploadId]);

        } catch (AwsException $e) {
            throw new \Exception('Failed to abort multipart upload: ' . $e->getMessage());
        }
    }

    /**
     * Generate a pre-signed URL for direct upload (single file, not multipart)
     *
     * @param int $visaApplicationId
     * @param string $filename
     * @param string $contentType
     * @return array{file_key: string, presigned_url: string, expiry: string}
     */
    public function generateDirectUploadUrl(int $visaApplicationId, string $filename, string $contentType): array
    {
        $fileKey = $this->generateFileKey($visaApplicationId, $filename);

        $command = $this->s3Client->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key' => $fileKey,
            'ContentType' => $contentType,
        ]);

        $request = $this->s3Client->createPresignedRequest($command, '+1 hour');
        $presignedUrl = (string) $request->getUri();

        return [
            'file_key' => $fileKey,
            'presigned_url' => $presignedUrl,
            'expiry' => now()->addHour()->toIso8601String(),
        ];
    }

    /**
     * Generate a unique file key for storage
     */
    private function generateFileKey(int $visaApplicationId, string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $timestamp = now()->format('Y-m-d_His');
        $uniqueId = substr(md5(uniqid()), 0, 8);

        return "visa-applications/{$visaApplicationId}/files/{$timestamp}_{$uniqueId}.{$extension}";
    }
}
