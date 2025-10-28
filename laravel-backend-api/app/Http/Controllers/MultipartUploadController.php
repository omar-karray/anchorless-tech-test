<?php

namespace App\Http\Controllers;

use App\Http\Requests\AbortMultipartUploadRequest;
use App\Http\Requests\CompleteMultipartUploadRequest;
use App\Http\Requests\InitiateMultipartUploadRequest;
use App\Models\VisaApplication;
use App\Services\DirectFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class MultipartUploadController extends BaseApiController
{
    public function __construct(
        private DirectFileUploadService $uploadService
    ) {}

    /**
     * Initiate a multipart upload session.
     * Returns upload ID and pre-signed URLs for each part.
     */
    public function initiate(InitiateMultipartUploadRequest $request, VisaApplication $visaApplication): JsonResponse
    {
        Gate::authorize('update', $visaApplication);

        $validated = $request->validated();

        try {
            $result = $this->uploadService->initiateMultipartUpload(
                visaApplication: $visaApplication,
                fileCategoryId: $validated['file_category_id'],
                fileName: $validated['file_name'],
                fileSize: $validated['file_size'],
                mimeType: $validated['mime_type'],
                totalParts: $validated['total_parts']
            );

            return $this->apiSuccess($result);
        } catch (\Exception $e) {
            return $this->apiError(
                'Failed to initiate multipart upload',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Complete a multipart upload session.
     * Assembles parts and creates the final file record.
     */
    public function complete(CompleteMultipartUploadRequest $request, VisaApplication $visaApplication): JsonResponse
    {
        Gate::authorize('update', $visaApplication);

        $validated = $request->validated();

        try {
            $file = $this->uploadService->completeMultipartUpload(
                visaApplication: $visaApplication,
                uploadId: $validated['upload_id'],
                parts: $validated['parts']
            );

            return $this->apiSuccess([
                'id' => $file->id,
                'file_name' => $file->file_name,
                'file_category_id' => $file->file_category_id,
                'uploaded_at' => $file->created_at->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return $this->apiError(
                'Failed to complete multipart upload',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Abort a multipart upload session.
     * Cleans up partial uploads.
     */
    public function abort(AbortMultipartUploadRequest $request, VisaApplication $visaApplication): JsonResponse
    {
        Gate::authorize('update', $visaApplication);

        $validated = $request->validated();

        try {
            $this->uploadService->abortMultipartUpload(
                uploadId: $validated['upload_id']
            );

            return $this->apiSuccess([
                'message' => 'Multipart upload aborted successfully',
            ]);
        } catch (\Exception $e) {
            return $this->apiError(
                'Failed to abort multipart upload',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['error' => $e->getMessage()]
            );
        }
    }
}
