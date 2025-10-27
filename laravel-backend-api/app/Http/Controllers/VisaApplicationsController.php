<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVisaApplicationRequest;
use App\Http\Requests\UpdateVisaApplicationRequest;
use App\Http\Resources\VisaApplicationResource;
use App\Events\VisaApplicationSubmitted;
use App\Models\VisaApplicantFile;
use App\Models\VisaApplication;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class VisaApplicationsController extends BaseApiController
{
    /**
     * Display a listing of the authenticated user's visa applications.
     */
    public function index(): JsonResponse
    {
        $applications = VisaApplication::with(['files.category'])
            ->where('applicant_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return $this->apiSuccess(
            VisaApplicationResource::collection($applications)->resolve()
        );
    }

    /**
     * Store a newly created visa application.
     */
    public function store(StoreVisaApplicationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['applicant_id'] = Auth::id();
        $data['status'] = $data['status'] ?? 'draft';

        if ($data['status'] === 'submitted') {
            $data['submitted_at'] = $data['submitted_at'] ?? now();
        }
        $application = VisaApplication::create($data)->load(['files.category']);

        if ($application->status === 'submitted') {
            event(new VisaApplicationSubmitted($application));
        }

        return $this->apiSuccess(
            VisaApplicationResource::make($application)->resolve(),
            Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified visa application.
     */
    public function show(VisaApplication $visaApplication): JsonResponse
    {
        $this->authorize('view', $visaApplication);

        return $this->apiSuccess(
            VisaApplicationResource::make(
                $visaApplication->load(['files.category'])
            )->resolve()
        );
    }

    /**
     * Update the specified visa application.
     */
    public function update(UpdateVisaApplicationRequest $request, VisaApplication $visaApplication): JsonResponse
    {
        $this->authorize('update', $visaApplication);

        $data = $request->validated();

        if (array_key_exists('status', $data)) {
            if ($data['status'] === 'submitted') {
                $data['submitted_at'] = isset($data['submitted_at'])
                    ? Carbon::parse($data['submitted_at'])
                    : now();
            } elseif (! array_key_exists('submitted_at', $data)) {
                $data['submitted_at'] = null;
            }
        } elseif (isset($data['submitted_at'])) {
            $data['submitted_at'] = Carbon::parse($data['submitted_at']);
        }

        $previousStatus = $visaApplication->status;

        $visaApplication->fill($data)->save();

        if ($previousStatus !== 'submitted'
            && $visaApplication->status === 'submitted'
            && $visaApplication->wasChanged('status')) {
            event(new VisaApplicationSubmitted($visaApplication->fresh()));
        }

        return $this->apiSuccess(
            VisaApplicationResource::make(
                $visaApplication->load(['files.category'])
            )->resolve()
        );
    }

    /**
     * Remove the specified visa application from storage.
     */
    public function destroy(VisaApplication $visaApplication): JsonResponse
    {
        $this->authorize('delete', $visaApplication);

        $visaApplication->load(['files']);

        $visaApplication->files->each(function (VisaApplicantFile $file): void {
            if ($file->path && Storage::disk($file->disk)->exists($file->path)) {
                Storage::disk($file->disk)->delete($file->path);
            }
            $file->delete();
        });

        $visaApplication->delete();

        return $this->apiSuccess([
            'message' => 'Visa application deleted successfully.',
            'deleted' => true,
        ]);
    }

}
