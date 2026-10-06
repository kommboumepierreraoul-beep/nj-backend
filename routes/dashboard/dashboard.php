<?php

// Routes du tableau de bord (cahier des charges NJ Global Trade v2, section 2.3 : "Tableau
// de bord et KPI temps reel"). Inclus depuis routes/api.php.

use App\Http\Controllers\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

// Lecture seule, derriere "dashboard.view" (meme modele que les autres modules : ADMIN par
// defaut, SUPER_ADMIN contourne toujours la verification — voir User::hasPermission()).
Route::middleware(['auth.api', 'password.changed'])->group(function (): void {
    Route::middleware('permission:dashboard.view')->group(function (): void {
        Route::get('dashboard/stats', [DashboardController::class, 'stats']);
        // Blocs complementaires (rangees 2 a 5 de la maquette) : performance mensuelle,
        // qualite du recouvrement, velocite du cycle de vie, panneaux modules, fils
        // d'activite. Sans parametre de periode (instantane "maintenant"/tout-historique).
        Route::get('dashboard/overview', [DashboardController::class, 'overview']);
        Route::get('dashboard/pending-sales-orders', [DashboardController::class, 'pendingSalesOrders']);
    });
});
