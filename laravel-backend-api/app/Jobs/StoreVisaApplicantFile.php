<?php

namespace App\Jobs;

use App\Models\VisaApplicantFile;
use App\Models\VisaApplication;
use App\Events\VisaApplicantFileStored;
use App\Events\VisaApplicantFileFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class StoreVisaApplicantFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $visaApplicationId,
        public int $applicantId,
        public int $fileCategoryId,
        public string $temporaryPath,
        public string $temporaryDisk,
        public string $originalName,
        public string $mimeType,
        public int $sizeBytes
    ) {
        $this->onQueue('file-uploads');
    }

    public function handle(): void
    {
        $visaApplication = VisaApplication::find($this->visaApplicationId);

        if (! $visaApplication) {
            $this->broadcastFailure('visa_application_missing');
            return;
        }

        $sourceDisk = Storage::disk($this->temporaryDisk);

        if (! $sourceDisk->exists($this->temporaryPath)) {
            $this->broadcastFailure('temporary_file_missing');
            return;
        }

        $disk = config('filesystems.default', 'local');
        $directory = sprintf('visa-applications/%s/files', $visaApplication->id);

        $stream = $sourceDisk->readStream($this->temporaryPath);
        if (! $stream) {
            $this->broadcastFailure('read_stream_failed');
            return;
        }
        $finalPath = $directory.'/'.basename($this->temporaryPath);

        $writeResult = Storage::disk($disk)->writeStream($finalPath, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($writeResult === false) {
            $this->broadcastFailure('write_stream_failed');
            return;
        }

        $visaApplicantFile = VisaApplicantFile::create([
            'visa_application_id' => $visaApplication->id,
            'applicant_id' => $this->applicantId,
            'file_category_id' => $this->fileCategoryId,
            'original_name' => $this->originalName,
            'stored_name' => basename($finalPath),
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
            'path' => $finalPath,
            'disk' => $disk,
        ]);

        $sourceDisk->delete($this->temporaryPath);

        event(new VisaApplicantFileStored($visaApplicantFile));
    }

    private function broadcastFailure(string $reason): void
    {
        event(new VisaApplicantFileFailed($this->visaApplicationId, $reason));
    }
}
