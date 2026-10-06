<?php

// Routes du module "authentification et comptes utilisateurs" : connexion,
// connexion Google, reinitialisation de mot de passe et session courante.
// La gestion des comptes utilisateurs (CRUD, statut, permissions) vit dans
// routes/users/users.php. Inclus depuis routes/api.php.

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    // Limitation de debit sur les routes publiques sensibles a la force brute / au spam.
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('google/redirect', [GoogleAuthController::class, 'redirect'])->middleware('throttle:20,1');
    Route::post('google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:10,1');
    Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:5,1');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1');

    Route::middleware('auth.api')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        // Bloc "Securite & sessions" (page B1) : l'utilisateur consulte et revoque
        // ses propres sessions actives. La revocation forcee d'un AUTRE utilisateur
        // par un administrateur vit dans routes/users/users.php (DELETE users/{user}/sessions).
        Route::get('sessions', [AuthController::class, 'sessions']);
        Route::delete('sessions/{tokenId}', [AuthController::class, 'revokeSession']);
    });
});
