<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisaApplicantFileRequest;
use App\Http\Resources\VisaApplicantFileResource;
use App\Models\VisaApplicantFile;
use App\Models\VisaApplication;
use App\Services\Contracts\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class VisaApplicantFilesController extends BaseApiController
{
    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {
    }

    /**
     * Display a list of files for a specific visa application.
     */
    public function index(VisaApplication $visaApplication): JsonResponse
    {
        $this->authorize('view', $visaApplication);

        $files = $visaApplication->files()
            ->with(['category', 'visaApplication'])
            ->orderByDesc('created_at')
            ->get();

        return $this->apiSuccess(
            VisaApplicantFileResource::collection($files)->resolve()
        );
    }

    /**
     * Store a newly uploaded file.
     */
    public function store(StoreVisaApplicantFileRequest $request, VisaApplication $visaApplication): JsonResponse
    {
        $this->authorize('update', $visaApplication);

        $data = $request->validated();

        try {
            $this->fileUploadService->queueVisaApplicantFile(
                $visaApplication,
                $request->file('file'),
                $data['file_category_id'],
                (int) $request->user()->getKey()
            );
        } catch (RuntimeException) {
            return $this->apiError(
                'Unable to queue file upload at this time.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->apiSuccess([
            'message' => 'File upload queued for processing.',
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Remove the specified file from storage.
     */
    public function destroy(VisaApplication $visaApplication, VisaApplicantFile $visaApplicantFile): JsonResponse
    {
        $this->authorize('view', $visaApplication);

        abort_if(
            $visaApplicantFile->visa_application_id !== $visaApplication->id,
            Response::HTTP_NOT_FOUND,
            'The file does not belong to this visa application.'
        );

        $this->authorize('delete', $visaApplicantFile);

        if ($visaApplicantFile->path && Storage::disk($visaApplicantFile->disk)->exists($visaApplicantFile->path)) {
            Storage::disk($visaApplicantFile->disk)->delete($visaApplicantFile->path);
        }

        $visaApplicantFile->delete();

        return $this->apiSuccess([
            'message' => 'Visa application file deleted successfully.',
            'deleted' => true,
        ]);
    }
}
