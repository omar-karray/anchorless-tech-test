<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('welcome');
});

// Broadcasting authentication for private/presence channels
Broadcast::routes(['middleware' => ['auth:sanctum']]);
