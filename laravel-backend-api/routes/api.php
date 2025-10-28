<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FileCategoriesController;
use App\Http\Controllers\TestBroadcastController;
use App\Http\Controllers\VisaApplicantFilesController;
use App\Http\Controllers\VisaApplicationsController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession as SanctumAuthenticateSession;

// ---------------------------------------------------------------
// Diagnostics
// ---------------------------------------------------------------
Route::post('diagnostics/broadcast-test', TestBroadcastController::class)
    ->name('diagnostics.broadcast-test');

// ---------------------------------------------------------------
// Authentication (API - Session-based for SPA)
// ---------------------------------------------------------------
// Login endpoint - establishes session cookie for SPA
Route::post('login', [AuthController::class, 'login'])->name('api.login');

// Logout endpoint - destroys session and clears cookies
Route::post('logout', [AuthController::class, 'logout'])->name('api.logout');

// Token-based authentication (for Postman / third parties). Returns a
// Sanctum Personal Access Token that can be used as `Authorization: Bearer <token>`.
Route::post('auth/token/create', [AuthController::class, 'tokenCreate'])->name('auth.token.create');

// ---------------------------------------------------------------
// Protected API using Sanctum (tokens or session when stateful)
// ---------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function (): void {
    // Session or token-authenticated user info (SPA uses this; works with cookies or tokens)
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
    // Token-only variant for tests and third-party clients (no session fallback). Not used by the SPA.
    // Only registered in local/testing environments.
    if (app()->environment(['local', 'testing'])) {
        Route::get('auth/me-token', [AuthController::class, 'meToken'])
            ->withoutMiddleware([SanctumAuthenticateSession::class])
            ->name('auth.me.token');
    }
    // Revoke the current personal access token (Bearer)
    Route::post('auth/token/revoke', [AuthController::class, 'tokenRevoke'])->name('auth.token.revoke');
    
    // -----------------------------------------------------------
    // File Categories (protected)
    // -----------------------------------------------------------
    Route::get('file-categories', [FileCategoriesController::class, 'index'])->name('file-categories.index');

    // -----------------------------------------------------------
    // Visa Applications (protected)
    // -----------------------------------------------------------
    Route::apiResource('visa-applications', VisaApplicationsController::class);

    Route::controller(VisaApplicantFilesController::class)->group(function (): void {
        Route::get('visa-applications/{visa_application}/files', 'index')->name('visa-applications.files.index');
        Route::post('visa-applications/{visa_application}/files', 'store')->name('visa-applications.files.store');
        Route::delete('visa-applications/{visa_application}/files/{visaApplicantFile}', 'destroy')->name('visa-applications.files.destroy');
    });
});
