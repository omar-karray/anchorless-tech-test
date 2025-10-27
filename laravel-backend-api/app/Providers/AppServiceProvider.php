<?php

namespace App\Providers;

use App\Models\VisaApplicantFile;
use App\Models\VisaApplication;
use App\Policies\VisaApplicantFilePolicy;
use App\Policies\VisaApplicationPolicy;
use App\Services\Contracts\FileUploadService as FileUploadServiceContract;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FileUploadServiceContract::class, FileUploadService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(VisaApplication::class, VisaApplicationPolicy::class);
        Gate::policy(VisaApplicantFile::class, VisaApplicantFilePolicy::class);
    }
}
