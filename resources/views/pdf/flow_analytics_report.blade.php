<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        {{-- dompdf ne supporte ni flexbox ni CSS grid (voir resources/views/pdf/invoice_proforma.blade.php) :
             mise en page en table simple. Police DejaVu Sans pour les caracteres accentues. --}}
        {{-- margin-bottom du @page = hauteur reservee au pied de page fixe (repete sur chaque page). --}}
        @page { size: A4; margin: 14mm 14mm 18mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        p.meta { color: #6b7280; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background-color: #f3f4f6; }
        tr:nth-child(even) td { background-color: #fafafa; }
        {{-- position: fixed => rendu en bas de CHAQUE page par dompdf. --}}
        .page-footer { position: fixed; left: 0; right: 0; bottom: 0;
            border-top: 1px solid #e5e7eb; padding-top: 4px; font-size: 8.5px; color: #9ca3af; }
        .page-footer .num:after { content: counter(page) " / " counter(pages); }
    </style>
</head>
<body>
    <div class="page-footer">
        NJ Global Trade Co. Ltd &mdash; {{ $title }} &mdash; genere le {{ now()->format('d/m/Y H:i') }} &mdash; page <span class="num"></span>
    </div>

    <h1>NJ Global Trade &mdash; {{ $title }}</h1>
    <p class="meta">
        Periode : {{ $period }} &mdash; du {{ $from->format('d/m/Y H:i') }} au {{ $to->format('d/m/Y H:i') }}<br>
        Genere le {{ now()->format('d/m/Y H:i') }}
    </p>
    <table>
        <thead>
            <tr>
                <th style="width: 60%;">Indicateur</th>
                <th>Valeur</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as [$label, $value])
                <tr>
                    <td>{{ $label }}</td>
                    <td>{{ $value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
