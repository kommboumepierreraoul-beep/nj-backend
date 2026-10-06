<?php

namespace App\Http\Controllers;

use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    // Garde-fou volontaire sur l'export : au-dela, le PDF devient illisible et la
    // generation lente. L'utilisateur affine les filtres (periode, module, acteur) —
    // l'entete du fichier signale la troncature le cas echeant.
    private const EXPORT_MAX_ROWS = 5000;

    /**
     * Page "Journal d'activite" (volet audit metier) : liste paginee, filtrable
     * par entite, acteur, action et periode. Lecture seule — aucune route
     * d'ecriture n'est exposee sur ce journal (Doc/audit_trace_systeme.md).
     */
    public function index(Request $request): JsonResponse
    {
        $logs = $this->filteredQuery($request)
            ->with('actor')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => AuditLogResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        return response()->json([
            'data' => new AuditLogResource($auditLog->load('actor')),
        ]);
    }

    /**
     * Export CSV/PDF du journal (Doc/design_system_maquette_complete.md §4.7 : "formats
     * PDF/Excel/CSV" ; point de cadrage spec_pages_audit.md §3.3, tranche). Reprend
     * exactement les memes filtres que index(). Reste une lecture : le journal demeure
     * immuable, aucune ecriture n'est introduite. Meme convention `format=csv|pdf` que
     * les exports du module Analyse des flux.
     */
    public function export(Request $request): StreamedResponse|Response
    {
        $request->validate(['format' => ['required', 'in:csv,pdf']]);

        $total = $this->filteredQuery($request)->count();
        $truncated = $total > self::EXPORT_MAX_ROWS;

        $logs = $this->filteredQuery($request)
            ->with('actor')
            ->orderByDesc('id')
            ->limit(self::EXPORT_MAX_ROWS)
            ->get();

        $stamp = now()->format('Ymd-His');

        return $request->input('format') === 'csv'
            ? $this->exportCsv($logs, "journal-activite-{$stamp}.csv")
            : $this->exportPdf($logs, $truncated ? self::EXPORT_MAX_ROWS : $logs->count(), $truncated, $this->filtersLabel($request), "journal-activite-{$stamp}.pdf");
    }

    private function filteredQuery(Request $request): Builder
    {
        return AuditLog::query()
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->input('entity_type')))
            ->when($request->filled('entity_id'), fn ($query) => $query->where('entity_id', $request->input('entity_id')))
            ->when($request->filled('actor_user_id'), fn ($query) => $query->where('actor_user_id', $request->input('actor_user_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->input('action')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('to')));
    }

    private function exportCsv($logs, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 : Excel ouvre alors le fichier avec le bon encodage sans manip.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Date', 'Acteur', 'Email acteur', 'Action', "Type d'entite", 'ID entite', 'IP', 'User-agent']);
            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->created_at?->format('Y-m-d H:i:s'),
                    $log->actor?->full_name ?? '',
                    $log->actor?->email ?? '',
                    $log->action,
                    $log->entity_type,
                    $log->entity_id,
                    $log->ip_address ?? '',
                    $log->user_agent ?? '',
                ]);
            }
            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf($logs, int $count, bool $truncated, string $filtersLabel, string $fileName): Response
    {
        // Meme facade dompdf que Invoice\ProformaController et FlowAnalytics\Concerns\
        // ExportsFlowReport : gabarit en table simple (ni flexbox ni grid).
        $pdf = Pdf::loadView('pdf.audit_log_report', [
            'logs' => $logs,
            'count' => $count,
            'truncated' => $truncated,
            'filtersLabel' => $filtersLabel,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream($fileName);
    }

    private function filtersLabel(Request $request): string
    {
        $parts = [];
        foreach (['entity_type' => 'entite', 'entity_id' => 'id', 'actor_user_id' => 'acteur', 'action' => 'action', 'from' => 'du', 'to' => 'au'] as $key => $label) {
            if ($request->filled($key)) {
                $parts[] = $label.' = '.$request->input($key);
            }
        }

        return $parts === [] ? 'aucun' : implode(', ', $parts);
    }
}
