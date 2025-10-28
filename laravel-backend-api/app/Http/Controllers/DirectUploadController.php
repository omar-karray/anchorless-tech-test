<?php

namespace App\Http\Controllers;

use App\Http\Requests\InitiateDirectUploadRequest;
use App\Http\Requests\CompleteDirectUploadRequest;
use App\Models\VisaApplication;
use App\Models\VisaApplicantFile;
use App\Services\DirectFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class DirectUploadController extends BaseApiController
{
    public function __construct(
        private DirectFileUploadService $uploadService
    ) {}

    /**
     * Generate a pre-signed URL for direct upload
     *
     * @param InitiateDirectUploadRequest $request
     * @param VisaApplication $visaApplication
     * @return JsonResponse
     */
    public function initiate(InitiateDirectUploadRequest $request, VisaApplication $visaApplication): JsonResponse
    {
        Gate::authorize('update', $visaApplication);

        $validated = $request->validated();

        try {
            $uploadData = $this->uploadService->generateDirectUploadUrl(
                $visaApplication->id,
                $validated['filename'],
                $validated['content_type']
            );

            return $this->apiSuccess($uploadData);

        } catch (\Exception $e) {
            return $this->apiError(
                'Failed to generate upload URL',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Confirm the direct upload and create database record
     *
     * @param CompleteDirectUploadRequest $request
     * @param VisaApplication $visaApplication
     * @return JsonResponse
     */
    public function complete(CompleteDirectUploadRequest $request, VisaApplication $visaApplication): JsonResponse
    {
        Gate::authorize('update', $visaApplication);

        $validated = $request->validated();

        try {
            // Create database record for the uploaded file
            $file = VisaApplicantFile::create([
                'visa_application_id' => $visaApplication->id,
                'applicant_id' => $visaApplication->applicant_id,
                'file_category_id' => $request->input('file_category_id', 1),
                'original_name' => $validated['filename'],
                'stored_name' => basename($validated['file_key']),
                'path' => $validated['file_key'],
                'size_bytes' => $validated['file_size'],
                'mime_type' => $validated['content_type'],
                'disk' => 'minio',
            ]);

            return $this->apiSuccess([
                'file' => [
                    'id' => $file->id,
                    'file_name' => $file->original_name,
                    'file_size' => $file->size_bytes,
                    'mime_type' => $file->mime_type,
                    'created_at' => $file->created_at->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            return $this->apiError(
                'Failed to complete upload',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['error' => $e->getMessage()]
            );
        }
    }
}
