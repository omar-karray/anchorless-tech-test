<?php

namespace App\Services;

use App\Jobs\StoreVisaApplicantFile;
use App\Models\VisaApplication;
use App\Services\Contracts\FileUploadService as FileUploadServiceContract;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class FileUploadService implements FileUploadServiceContract
{
    public function queueVisaApplicantFile(
        VisaApplication $visaApplication,
        UploadedFile $uploadedFile,
        int $fileCategoryId,
        int $userId
    ): void {
        $temporaryDisk = config('filesystems.default', 'local');
        $temporaryDirectory = sprintf('tmp/visa-applications/%s', $visaApplication->id);

        $originalName = $uploadedFile->getClientOriginalName();
        $mimeType = $uploadedFile->getClientMimeType() ?? $uploadedFile->getMimeType();
        $sizeBytes = $uploadedFile->getSize() ?? 0;

        $temporaryPath = $uploadedFile->store($temporaryDirectory, $temporaryDisk);

        if (! is_string($temporaryPath)) {
            throw new RuntimeException('Unable to persist temporary upload.');
        }

        StoreVisaApplicantFile::dispatch(
            $visaApplication->id,
            $userId,
            $fileCategoryId,
            $temporaryPath,
            $temporaryDisk,
            $originalName,
            $mimeType,
            $sizeBytes
        );
    }
}
