<?php

// Routes du module "gestion des utilisateurs & permissions" : CRUD utilisateurs,
// statut (activation/desactivation), suppression definitive, permissions
// individuelles et gestion des sessions par un administrateur. Inclus depuis
// routes/api.php. Voir Doc/spec_pages_utilisateurs.md pour la specification
// complete du module.

use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

// Gestion des utilisateurs : lecture derriere "users.view", ecriture derriere
// "users.manage". La desactivation/reactivation et la suppression definitive
// sont, en plus, reservees au role SUPER_ADMIN (verifie dans le controleur,
// voir Doc/spec_pages_utilisateurs.md section 10, decision #2). Le pilotage
// des permissions individuelles est protege par la permission delegable
// "users.manage_permissions" (decision #3), non attribuee par defaut a ADMIN.
// SUPER_ADMIN contourne toujours la verification de permission (voir
// User::hasPermission()).
Route::middleware(['auth.api', 'password.changed'])->group(function (): void {
    Route::middleware('permission:users.view')->group(function (): void {
        Route::get('users', [UserManagementController::class, 'index']);
        Route::get('users/{user}', [UserManagementController::class, 'show']);

        // D1 - catalogue des permissions (lecture seule, codes crees par
        // migration/seed). Consomme aussi par le selecteur "Ajouter une
        // permission" de la fiche utilisateur (C2).
        Route::get('permissions', [PermissionController::class, 'index']);
    });

    Route::middleware('permission:users.manage')->group(function (): void {
        Route::post('users', [UserManagementController::class, 'store']);
        Route::put('users/{user}', [UserManagementController::class, 'update']);
        Route::put('users/{user}/status', [UserManagementController::class, 'updateStatus']);
        Route::delete('users/{user}', [UserManagementController::class, 'destroy']);
        Route::delete('users/{user}/sessions', [UserManagementController::class, 'revokeSessions']);
    });

    Route::middleware('permission:users.manage_permissions')->group(function (): void {
        Route::put('users/{user}/permissions', [UserManagementController::class, 'updatePermissions']);

        // D2 - matrice permissions par role (base commune a tous les ADMIN).
        // Le SUPER_ADMIN est en lecture seule cote controleur : il contourne
        // toujours la verification, une entree permission_role serait inerte.
        Route::get('roles/{role}/permissions', [PermissionController::class, 'showRole']);
        Route::put('roles/{role}/permissions', [PermissionController::class, 'updateRole']);
    });
});
