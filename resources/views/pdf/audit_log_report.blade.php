<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        {{-- dompdf ne supporte ni flexbox ni CSS grid (voir resources/views/pdf/flow_analytics_report.blade.php) :
             mise en page en table simple. Police DejaVu Sans pour les caracteres accentues.
             margin-bottom du @page = hauteur reservee au pied de page fixe (repete sur chaque page). --}}
        @page { margin: 14mm 12mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 15px; margin-bottom: 4px; }
        p.meta { color: #6b7280; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background-color: #f3f4f6; }
        tr:nth-child(even) td { background-color: #fafafa; }
        td.entity { white-space: nowrap; }
        {{-- position: fixed => rendu en bas de CHAQUE page par dompdf. --}}
        .page-footer { position: fixed; left: 0; right: 0; bottom: 0;
            border-top: 1px solid #e5e7eb; padding-top: 4px; font-size: 8px; color: #9ca3af; }
        .page-footer .num:after { content: counter(page) " / " counter(pages); }
    </style>
</head>
<body>
    <div class="page-footer">
        NJ Global Trade Co. Ltd &mdash; journal d'activite genere le {{ now()->format('d/m/Y H:i') }} &mdash; page <span class="num"></span>
    </div>

    <h1>NJ Global Trade &mdash; Journal d'activite</h1>
    <p class="meta">
        {{ $count }} entree(s){{ $truncated ? ' (limite a '.$count.', export tronque — affinez les filtres)' : '' }}<br>
        Filtres : {{ $filtersLabel }}<br>
        Genere le {{ now()->format('d/m/Y H:i') }}
    </p>
    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Date</th>
                <th style="width: 18%;">Acteur</th>
                <th style="width: 22%;">Action</th>
                <th style="width: 18%;">Entite</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $log->actor?->full_name ?? '—' }}</td>
                    <td>{{ $log->action }}</td>
                    <td class="entity">{{ $log->entity_type }} #{{ $log->entity_id }}</td>
                    <td>{{ $log->ip_address ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
