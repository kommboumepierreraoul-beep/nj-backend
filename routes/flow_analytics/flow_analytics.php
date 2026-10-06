<?php

// Routes du module "Analyse des flux" (Doc/analyse_flux_modele_donnees.md) : indicateurs de
// parcours achat/vente/financier/activite, vue transversale des goulots d'etranglement,
// export CSV/PDF, et parametrage des seuils d'alerte. Inclus depuis routes/api.php.

use App\Http\Controllers\FlowAnalytics\FlowAnalyticsController;
use App\Http\Controllers\FlowAnalytics\FlowStageThresholdController;
use Illuminate\Support\Facades\Route;

// Lecture (+ export) derriere "flow_analytics.view", parametrage des seuils derriere
// "flow_analytics.manage" -- meme modele que les autres modules (ADMIN par defaut,
// SUPER_ADMIN contourne toujours la verification, voir User::hasPermission()).
Route::middleware(['auth.api', 'password.changed'])->group(function (): void {
    Route::middleware('permission:flow_analytics.view')->group(function (): void {
        Route::get('flow-analytics/purchase-flow', [FlowAnalyticsController::class, 'purchaseFlow']);
        Route::get('flow-analytics/sales-flow', [FlowAnalyticsController::class, 'salesFlow']);
        Route::get('flow-analytics/financial', [FlowAnalyticsController::class, 'financial']);
        Route::get('flow-analytics/activity-flow', [FlowAnalyticsController::class, 'activityFlow']);
        Route::get('flow-analytics/bottlenecks', [FlowAnalyticsController::class, 'bottlenecks']);
        Route::get('flow-analytics/{flow}/export', [FlowAnalyticsController::class, 'export']);

        Route::get('flow-stage-thresholds', [FlowStageThresholdController::class, 'index']);
        Route::get('flow-stage-thresholds/{flowStageThreshold}', [FlowStageThresholdController::class, 'show']);
    });

    Route::middleware('permission:flow_analytics.manage')->group(function (): void {
        Route::post('flow-stage-thresholds', [FlowStageThresholdController::class, 'store']);
        Route::put('flow-stage-thresholds/{flowStageThreshold}', [FlowStageThresholdController::class, 'update']);
        Route::delete('flow-stage-thresholds/{flowStageThreshold}', [FlowStageThresholdController::class, 'destroy']);
    });
});
