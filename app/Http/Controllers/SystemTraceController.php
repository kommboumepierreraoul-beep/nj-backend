<?php

namespace App\Http\Controllers;

use App\Http\Resources\SystemTraceResource;
use App\Models\SystemTrace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemTraceController extends Controller
{
    /**
     * Volet "trace systeme" : liste paginee des evenements techniques et de
     * securite (connexions, deconnexions, acces refuses...), filtrable par
     * evenement, utilisateur, IP et periode. Lecture seule.
     */
    public function index(Request $request): JsonResponse
    {
        $traces = SystemTrace::query()
            ->with('user')
            ->when($request->filled('event'), fn ($query) => $query->where('event', $request->input('event')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->input('user_id')))
            ->when($request->filled('ip_address'), fn ($query) => $query->where('ip_address', $request->input('ip_address')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('to')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => SystemTraceResource::collection($traces),
            'meta' => [
                'current_page' => $traces->currentPage(),
                'last_page' => $traces->lastPage(),
                'total' => $traces->total(),
            ],
        ]);
    }
}
