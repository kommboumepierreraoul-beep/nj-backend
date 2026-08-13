<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink']);
    Route::post('reset-password', [PasswordResetController::class, 'reset']);

    Route::middleware('auth.api')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware(['auth.api', 'role:SUPER_ADMIN,ADMIN'])->group(function (): void {
    Route::post('users', [UserManagementController::class, 'store']);
});
