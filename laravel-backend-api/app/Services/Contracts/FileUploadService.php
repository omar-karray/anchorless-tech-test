<?php

namespace App\Services\Contracts;

use App\Models\VisaApplication;
use Illuminate\Http\UploadedFile;

interface FileUploadService
{
    public function queueVisaApplicantFile(
        VisaApplication $visaApplication,
        UploadedFile $uploadedFile,
        int $fileCategoryId,
        int $userId
    ): void;
}
