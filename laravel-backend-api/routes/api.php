<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\VisaApplicantFilesController;
use App\Http\Controllers\VisaApplicationsController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
Route::post('login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::apiResource('visa-applications', VisaApplicationsController::class);

    Route::controller(VisaApplicantFilesController::class)->group(function (): void {
        Route::get('visa-applications/{visa_application}/files', 'index')->name('visa-applications.files.index');
        Route::post('visa-applications/{visa_application}/files', 'store')->name('visa-applications.files.store');
        Route::delete('visa-applications/{visa_application}/files/{visaApplicantFile}', 'destroy')->name('visa-applications.files.destroy');
    });
});
