<?php

namespace App\Http\Controllers\SalesOrder;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;

class SalesOrderStatusHistoryController extends Controller
{
    // Timeline "metier" affichee sur la fiche commande (Doc/commandes_modele_donnees.md,
    // section 5) : lecture seule, alimentee uniquement par
    // SalesOrderController::updateStatus().
    public function index(SalesOrder $salesOrder): JsonResponse
    {
        return response()->json([
            'data' => $salesOrder->statusHistory()->with('changedBy')->orderByDesc('id')->get(),
        ]);
    }
}
