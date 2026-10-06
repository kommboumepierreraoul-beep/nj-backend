<?php

// Routes du module "Notifications" (Doc/notifications_modele_donnees.md) : centre de
// notifications internes et preferences de canal. Inclus depuis routes/api.php.

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

// Pas de middleware "permission:" ici (decision §2.5 du cadrage) : ressource strictement
// personnelle, chaque endpoint est restreint a l'utilisateur authentifie a l'interieur du
// controleur, jamais par role/permission.
Route::middleware(['auth.api', 'password.changed'])->group(function (): void {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead']);

    Route::get('notification-preferences', [NotificationPreferenceController::class, 'index']);
    Route::put('notification-preferences', [NotificationPreferenceController::class, 'update']);
});
