<?php

namespace App\Http\Controllers\FlowAnalytics\Concerns;

use App\Enums\DashboardPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV/PDF des rapports du module Analyse des flux (Doc/analyse_flux_modele_donnees.md,
 * §3bis, ajoute au perimetre le 2026-08-26). Aplatit le tableau associatif renvoye par
 * chaque "build*Flow()" en lignes libelle/valeur -- volontairement generique (un seul
 * gabarit tabulaire, pas 5 mises en page dediees) pour couvrir les 5 rapports
 * (achat/vente/financier/activite/goulots) sans multiplier les templates PDF.
 */
trait ExportsFlowReport
{
    private function exportReport(string $format, string $flow, string $title, DashboardPeriod $period, Carbon $from, Carbon $to, array $data): Response
    {
        $rows = $this->flattenForExport($data);

        return $format === 'csv'
            ? $this->exportCsv($flow, $from, $to, $rows)
            : $this->exportPdf($flow, $title, $period, $from, $to, $rows);
    }

    private function exportCsv(string $flow, Carbon $from, Carbon $to, array $rows): StreamedResponse
    {
        $fileName = $flow.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Indicateur', 'Valeur']);
            foreach ($rows as [$label, $value]) {
                fputcsv($handle, [$label, $value]);
            }
            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportPdf(string $flow, string $title, DashboardPeriod $period, Carbon $from, Carbon $to, array $rows): Response
    {
        // Meme facade que Invoice\ProformaController (Doc/invoice_model.md) : dompdf ne
        // supporte ni flexbox ni CSS grid, le gabarit reste volontairement une simple table.
        $pdf = Pdf::loadView('pdf.flow_analytics_report', [
            'title' => $title,
            'period' => $period->value,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
        ])->setPaper('a4');

        $fileName = $flow.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf';

        return $pdf->stream($fileName);
    }

    /**
     * Aplatit un tableau associatif (potentiellement imbrique) en paires
     * [libelle, valeur] lisibles -- ex. ['tresorerie' => ['encaisse' => 100]] devient
     * [['tresorerie > encaisse', 100]]. Les listes/collections non-associatives (ex.
     * performance_par_fournisseur) sont rendues en JSON compact plutot qu'aplaties
     * recursivement -- resteraient illisibles en ligne/colonne autrement.
     */
    private function flattenForExport(array $data, string $prefix = ''): array
    {
        $rows = [];

        foreach ($data as $key => $value) {
            $label = $prefix === '' ? (string) $key : $prefix.' > '.$key;

            if ($value instanceof Collection) {
                $value = $value->all();
            }

            if (is_array($value)) {
                if ($this->isAssociative($value)) {
                    $rows = array_merge($rows, $this->flattenForExport($value, $label));

                    continue;
                }

                $rows[] = [$label, json_encode($value, JSON_UNESCAPED_UNICODE)];

                continue;
            }

            $rows[] = [$label, $value ?? '—'];
        }

        return $rows;
    }

    private function isAssociative(array $array): bool
    {
        return $array !== [] && array_keys($array) !== range(0, count($array) - 1);
    }
}
