<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<title>Proforma {{ $invoice->invoice_number }} — Comparatif</title>
<style>
  {{--
    Gabarit dompdf du comparatif (Doc/proforma_comparatif_addendum.md) : reprend
    telles quelles les conventions CSS deja eprouvees de pdf.invoice_proforma
    (table/float uniquement, dompdf ne supportant ni flexbox ni CSS grid ; pas de
    box-shadow ni degrade). Palette et chrome (bandeau noir + liseré doré) inchanges
    par rapport a la maquette approuvee (Doc/proforma_mockup_reference.html) —
    decision n°7 de l'addendum : seule la structure/contenu change ici, pas la
    charte graphique.
  --}}
  {{-- margin-bottom reserve la hauteur du pied de page fixe (repete sur chaque page). --}}
  @page { margin: 0 0 52px; }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    {{-- DejaVu Sans (bundle avec dompdf) : necessaire pour "≈" (poids/volume indicatifs)
         comme dans pdf.invoice_proforma — Helvetica seule ne contient pas ce caractere. --}}
    font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
    color: #1a1a1a;
    font-size: 11.5px;
    line-height: 1.45;
  }
  table { border-collapse: collapse; width: 100%; }

  .header-table { padding: 22px 32px 14px; background: #ffffff; }
  .header-table td { vertical-align: top; }
  .header-table img.logo { height: 56px; }
  .doc-meta { text-align: right; }
  .doc-meta .doc-title {
    font-size: 20px;
    font-weight: 800;
    color: #111;
    letter-spacing: 0.5px;
    margin: 0 0 2px;
  }
  .doc-meta .doc-sub { font-size: 11px; color: #6b6b6b; margin: 0; }

  .band-table { background: #F7CA0F; }
  .band-table td { padding: 10px 32px; font-size: 12px; color: #1a1a1a; vertical-align: middle; }
  .band-table .ref { font-weight: 800; font-size: 14px; }
  .band-table .dates { text-align: right; }
  .band-table .dates div { line-height: 1.4; }

  .body-wrap { padding: 20px 32px 0; }

  .black-bar {
    background: #111;
    color: #fff;
    padding: 8px 14px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    margin-bottom: 0;
  }
  .section { margin-bottom: 18px; }

  table.info-table { border: 1px solid #e5e5e5; border-top: none; }
  table.info-table td {
    width: 50%;
    border: 1px solid #e5e5e5;
    border-top: none;
    padding: 8px 12px;
    vertical-align: top;
  }
  .field-label {
    font-size: 9px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #8a8a8a;
    margin-bottom: 3px;
  }
  .field-value { font-size: 12px; font-weight: 700; color: #1a1a1a; min-height: 14px; }

  table.product-table { border: 1px solid #e5e5e5; border-top: none; }
  table.product-table td { padding: 12px; vertical-align: top; border: none; }
  .product-image-cell { width: 34%; padding-right: 4px !important; }
  .product-image-cell img { width: 100%; max-height: 150px; }
  .product-image-placeholder {
    background: #f4f4f2;
    border: 1px dashed #cccccc;
    height: 130px;
    text-align: center;
    color: #999;
    font-size: 10px;
    padding-top: 55px;
  }
  .product-name { font-size: 13px; font-weight: 800; color: #111; margin: 0 0 6px; }
  .product-desc { font-size: 11px; color: #444; margin: 0 0 10px; }
  .colis-type { font-size: 10.5px; color: #333; }
  .colis-type .active { font-weight: 800; color: #111; }

  table.comparatif-table { font-size: 10.8px; border: 1px solid #e5e5e5; border-top: none; }
  table.comparatif-table th {
    background: #111;
    color: #fff;
    padding: 9px 10px;
    font-weight: 700;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    text-align: left;
  }
  table.comparatif-table th .subtitle {
    display: block;
    font-weight: 400;
    font-size: 8.8px;
    text-transform: none;
    letter-spacing: 0;
    margin-top: 2px;
    color: #F7CA0F;
  }
  table.comparatif-table td {
    padding: 7px 10px;
    border-bottom: 1px solid #eee;
    vertical-align: top;
  }
  table.comparatif-table td.crit-label {
    font-weight: 700;
    background: #FAFAF7;
    width: 22%;
  }
  table.comparatif-table td.col-1 { background: #F5FAF5; }
  table.comparatif-table td.col-2 { background: #FFF9F1; }
  table.comparatif-table td.col-3 { background: #F1F5FB; }
  table.comparatif-table tr.row-commission td { color: #444; font-style: italic; }
  table.comparatif-table tr.row-total td {
    border-top: 2px solid #111;
    border-bottom: none;
    font-weight: 800;
    font-size: 12px;
  }
  table.comparatif-table tr.row-recommandation td {
    font-style: italic;
    color: #6b5900;
  }

  .page-break { page-break-before: always; }

  table.logistics-table { border: 1px solid #e5e5e5; border-top: none; font-size: 11px; }
  table.logistics-table th {
    background: #111;
    color: #fff;
    padding: 8px 10px;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    text-align: left;
  }
  table.logistics-table td {
    padding: 10px;
    border-bottom: 1px solid #eee;
    vertical-align: top;
  }
  table.logistics-table .mode-label { font-weight: 800; }
  table.logistics-table .muted { color: #999; font-style: italic; }

  table.avantages-table td {
    width: 33.33%;
    border: 1px solid #e5e5e5;
    padding: 10px 12px;
    vertical-align: top;
  }
  .avantages-table h4 {
    font-size: 9.5px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #8a8a8a;
    margin: 0 0 6px;
  }
  .avantages-table ul { margin: 0 0 10px; padding-left: 14px; }
  .avantages-table li { font-size: 10.5px; margin-bottom: 3px; }
  .avantages-table .empty { font-size: 10px; color: #bbb; font-style: italic; }

  table.notes-table td {
    border: 1px solid #e5e5e5;
    padding: 8px 12px;
    font-size: 11px;
  }
  table.notes-table td.notes-label { width: 22%; font-weight: 700; background: #FAFAF7; }

  .conclusion {
    background: #FEF9E7;
    border: 1px solid #F7CA0F;
    border-radius: 8px;
    padding: 16px 20px;
    margin: 22px 32px 0;
    font-size: 11.5px;
  }
  .conclusion strong { color: #8a6d00; }
  .conclusion .validity {
    font-weight: 800;
    color: #111;
    font-size: 12.5px;
    margin-bottom: 6px;
  }

  {{-- position: fixed => dompdf rend cet element en bas de CHAQUE page. --}}
  .footer-table { position: fixed; left: 0; right: 0; bottom: 0; background: #111; padding: 14px 32px; font-size: 9.5px; }
  .footer-table .company { color: #F7CA0F; font-weight: 700; }
  .footer-table .meta { text-align: right; color: #9a9a9a; }
  .footer-table .pagenum:after { content: counter(page) " / " counter(pages); }
</style>
</head>
<body>

@php
  $formatMoney = function ($amount, $code) {
      return number_format((float) $amount, 0, ',', ' ').' '.$code;
  };
  $formatQty = function ($value, $decimals = 2) {
      return $value === null ? '—' : rtrim(rtrim(number_format((float) $value, $decimals, ',', ' '), '0'), ',');
  };
  $currencyCode = $invoice->currency->code;
  $transportModeValue = $invoice->transport_mode?->value;
@endphp

<table class="header-table">
  <tr>
    <td style="width: 40%;">
      @if($logoBase64)
        <img class="logo" src="{{ $logoBase64 }}">
      @endif
    </td>
    <td class="doc-meta" style="width: 60%;">
      <p class="doc-title">{{ __('documents.proforma.title_comparatif') }}</p>
      <p class="doc-sub">{{ __('documents.proforma.subtitle') }}</p>
    </td>
  </tr>
</table>

<table class="band-table">
  <tr>
    <td class="ref">{{ __('documents.common.reference') }} : {{ $invoice->invoice_number }}</td>
    <td class="dates">
      <div>{{ __('documents.common.issue_date') }} : {{ optional($invoice->issued_at)->format('d/m/Y') }}</div>
      @if($invoice->due_date)
        <div>{{ __('documents.common.valid_until') }} : <strong>{{ \Illuminate\Support\Carbon::parse($invoice->due_date)->format('d/m/Y') }}</strong></div>
      @endif
    </td>
  </tr>
</table>

<div class="body-wrap">

  {{-- INFORMATIONS CLIENT --}}
  <div class="section">
    <div class="black-bar">{{ __('documents.comparatif.client_info') }}</div>
    <table class="info-table">
      <tr>
        <td>
          <div class="field-label">{{ __('documents.comparatif.client_name') }}</div>
          <div class="field-value">{{ $invoice->client_name }}</div>
        </td>
        <td>
          <div class="field-label">{{ __('documents.comparatif.country') }}</div>
          <div class="field-value">{{ $invoice->client?->country?->name ?? '—' }}</div>
        </td>
      </tr>
      <tr>
        <td>
          <div class="field-label">{{ __('documents.comparatif.phone') }}</div>
          <div class="field-value">{{ $invoice->client?->preferredContact()?->value ?? '—' }}</div>
        </td>
        <td>
          {{-- "Quantité" du wireframe (bloc INFORMATIONS CLIENT) : champ ambigu, distinct
               de "Quantité commandée" par option ci-dessous — laisse vide plutot que
               deviner sa source (deviation documentee, voir rapport). --}}
          <div class="field-label">{{ __('documents.items.quantity') }}</div>
          <div class="field-value">&nbsp;</div>
        </td>
      </tr>
      <tr>
        <td>
          <div class="field-label">{{ __('documents.comparatif.company') }}</div>
          <div class="field-value">{{ $invoice->client?->legal_name ?? '—' }}</div>
        </td>
        <td>
          <div class="field-label">{{ __('documents.comparatif.address_city') }}</div>
          <div class="field-value">{{ $invoice->client_address ?? '—' }}</div>
        </td>
      </tr>
    </table>
  </div>

  {{-- PRODUIT / BESOIN --}}
  <div class="section">
    <div class="black-bar">{{ __('documents.comparatif.product_need') }}</div>
    <table class="product-table">
      <tr>
        <td class="product-image-cell">
          @if($productImageBase64)
            <img src="{{ $productImageBase64 }}">
          @else
            <div class="product-image-placeholder">{{ __('documents.comparatif.package_image_placeholder') }}</div>
          @endif
        </td>
        <td>
          <div class="field-label">{{ __('documents.comparatif.product_name') }}</div>
          <p class="product-name">{{ $product->name ?? '—' }}</p>
          <div class="field-label">{{ __('documents.comparatif.description') }}</div>
          <p class="product-desc">{{ $product->description ?? '—' }}</p>
          <div class="field-label">{{ __('documents.comparatif.package_type') }}</div>
          <p class="colis-type">
            <span class="{{ $transportModeValue === 'AERIEN_STANDARD' ? 'active' : '' }}">[{{ $transportModeValue === 'AERIEN_STANDARD' ? 'X' : ' ' }}] {{ __('documents.comparatif.package_standard') }}</span>
            &nbsp;&nbsp;
            <span class="{{ $transportModeValue === 'AERIEN_SENSIBLE' ? 'active' : '' }}">[{{ $transportModeValue === 'AERIEN_SENSIBLE' ? 'X' : ' ' }}] {{ __('documents.comparatif.package_sensitive') }}</span>
          </p>
        </td>
      </tr>
    </table>
  </div>

  {{-- COMPARATIF DES OPTIONS --}}
  <div class="section">
    <div class="black-bar">{{ __('documents.comparatif.options_comparison') }}</div>
    <table class="comparatif-table">
      <thead>
        <tr>
          <th>{{ __('documents.comparatif.criteria') }}</th>
          @foreach($options as $option)
            <th>{{ $option['label']['title'] }}@if($option['label']['subtitle'])<span class="subtitle">{{ $option['label']['subtitle'] }}</span>@endif</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="crit-label">{{ __('documents.comparatif.level') }}</td>
          @foreach($options as $index => $option)
            <td class="col-{{ $index + 1 }}">{{ $option['label']['title'] }}</td>
          @endforeach
        </tr>
        @foreach($criteria as $criterion)
          <tr>
            <td class="crit-label">{{ $criterion->name }}</td>
            @foreach($options as $index => $option)
              <td class="col-{{ $index + 1 }}">{{ $option['attribute_values'][$criterion->id] ?? '—' }}</td>
            @endforeach
          </tr>
        @endforeach
        <tr>
          <td class="crit-label">{{ __('documents.transport.est_weight') }}</td>
          @foreach($options as $index => $option)
            <td class="col-{{ $index + 1 }}">{{ $option['estimated_weight_kg'] !== null ? '≈ '.$formatQty($option['estimated_weight_kg'], 3).' kg' : '—' }}</td>
          @endforeach
        </tr>
        <tr>
          <td class="crit-label">{{ __('documents.comparatif.moq') }}</td>
          @foreach($options as $index => $option)
            <td class="col-{{ $index + 1 }}">{{ $option['moq'] !== null ? $option['moq'] : '—' }}</td>
          @endforeach
        </tr>
        <tr>
          <td class="crit-label">{{ __('documents.items.unit_price') }}</td>
          @foreach($options as $index => $option)
            <td class="col-{{ $index + 1 }}">{{ $formatMoney($option['item']->unit_price, $currencyCode) }}</td>
          @endforeach
        </tr>
        <tr>
          <td class="crit-label">{{ __('documents.comparatif.quantity_ordered') }}</td>
          @foreach($options as $index => $option)
            <td class="col-{{ $index + 1 }}">{{ $formatQty($option['item']->quantity) }}</td>
          @endforeach
        </tr>
        <tr>
          <td class="crit-label">{{ __('documents.items.subtotal') }}</td>
          @foreach($options as $index => $option)
            <td class="col-{{ $index + 1 }}">{{ $formatMoney($option['item']->subtotal, $currencyCode) }}</td>
          @endforeach
        </tr>
        <tr class="row-commission">
          <td class="crit-label">{{ __('documents.summary.commission') }}</td>
          @foreach($options as $index => $option)
            <td class="col-{{ $index + 1 }}">{{ $formatMoney($option['commission_amount'], $currencyCode) }}</td>
          @endforeach
        </tr>
        @if(collect($options)->contains(fn ($o) => ($o['tax_amount'] ?? 0) > 0))
          <tr class="row-commission">
            <td class="crit-label">{{ __('documents.summary.vat') }}@php $r = collect($options)->pluck('tax_rate')->first(fn ($v) => $v); @endphp @if($r)({{ rtrim(rtrim(number_format((float) $r, 2, ',', ' '), '0'), ',') }} %)@endif</td>
            @foreach($options as $index => $option)
              <td class="col-{{ $index + 1 }}">{{ $formatMoney($option['tax_amount'] ?? 0, $currencyCode) }}</td>
            @endforeach
          </tr>
        @endif
        <tr class="row-total">
          <td class="crit-label">{{ collect($options)->contains(fn ($o) => ($o['tax_amount'] ?? 0) > 0) ? __('documents.summary.total_incl_tax') : __('documents.summary.total') }}</td>
          @foreach($options as $index => $option)
            <td class="col-{{ $index + 1 }}">{{ $formatMoney($option['total_amount'], $currencyCode) }}</td>
          @endforeach
        </tr>
        <tr class="row-recommandation">
          <td class="crit-label">{{ __('documents.comparatif.recommendation') }}</td>
          @foreach($options as $index => $option)
            <td class="col-{{ $index + 1 }}">{{ $option['recommandation'] ?: '—' }}</td>
          @endforeach
        </tr>
      </tbody>
    </table>
  </div>

</div>

{{-- LOGISTIQUE & CONSEILS --}}
<div class="body-wrap page-break">

  <div class="section">
    <div class="black-bar">{{ __('documents.comparatif.professional_advice') }}</div>
    <table class="logistics-table">
      <thead>
        <tr>
          <th>{{ __('documents.comparatif.option') }}</th>
          <th>{{ __('documents.comparatif.parameters') }}</th>
          <th>{{ __('documents.comparatif.estimate') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="mode-label">{{ __('documents.comparatif.air') }}</td>
          @if(isset($shipping['AERIEN']))
            <td>
              {{ __('documents.comparatif.lead') }} : {{ $shipping['AERIEN']['lead_time_label'] }}<br>
              {{ __('documents.comparatif.rate') }} : {{ number_format($shipping['AERIEN']['rate'], 0, ',', ' ') }} {{ $currencyCode }}/{{ $shipping['AERIEN']['unit'] }}<br>
              {{ __('documents.transport.est_weight') }} : {{ $formatQty($shipping['AERIEN']['quantity'], 3) }} {{ $shipping['AERIEN']['unit'] }}
            </td>
            <td>{{ __('documents.comparatif.estimated_cost') }} : {{ $formatMoney($shipping['AERIEN']['estimated_cost'], $currencyCode) }}</td>
          @else
            <td class="muted" colspan="2">{{ __('documents.comparatif.unavailable_air') }}</td>
          @endif
        </tr>
        <tr>
          <td class="mode-label">{{ __('documents.comparatif.sea') }}</td>
          @if(isset($shipping['MARITIME']))
            <td>
              {{ __('documents.comparatif.lead') }} : {{ $shipping['MARITIME']['lead_time_label'] }}<br>
              {{ __('documents.comparatif.rate') }} : {{ number_format($shipping['MARITIME']['rate'], 0, ',', ' ') }} {{ $currencyCode }}/{{ $shipping['MARITIME']['unit'] }}<br>
              {{ __('documents.transport.est_volume') }} : {{ $formatQty($shipping['MARITIME']['quantity'], 4) }} {{ $shipping['MARITIME']['unit'] }}
            </td>
            <td>{{ __('documents.comparatif.estimated_cost') }} : {{ $formatMoney($shipping['MARITIME']['estimated_cost'], $currencyCode) }}</td>
          @else
            <td class="muted" colspan="2">{{ __('documents.comparatif.unavailable_sea') }}</td>
          @endif
        </tr>
      </tbody>
    </table>
  </div>

  <div class="section">
    <div class="black-bar">{{ __('documents.comparatif.details_advantages') }}</div>
    <table class="avantages-table">
      <tr>
        @foreach($options as $option)
          <td>
            <h4>{{ __('documents.comparatif.strengths_for', ['option' => $option['label']['title']]) }}</h4>
            @if(count($option['points_forts']))
              <ul>
                @foreach($option['points_forts'] as $point)
                  <li>{{ $point }}</li>
                @endforeach
              </ul>
            @else
              <p class="empty">{{ __('documents.comparatif.not_provided') }}</p>
            @endif
          </td>
        @endforeach
      </tr>
      <tr>
        @foreach($options as $option)
          <td>
            <h4>{{ __('documents.comparatif.attention_points') }}</h4>
            @if(count($option['points_attention']))
              <ul>
                @foreach($option['points_attention'] as $point)
                  <li>{{ $point }}</li>
                @endforeach
              </ul>
            @else
              <p class="empty">{{ __('documents.comparatif.not_provided') }}</p>
            @endif
          </td>
        @endforeach
      </tr>
    </table>
  </div>

  @php $notes = $invoice->proposal_details['notes'] ?? []; @endphp
  <div class="section">
    <div class="black-bar">{{ __('documents.comparatif.notes_conditions') }}</div>
    <table class="notes-table">
      <tr>
        <td class="notes-label">{{ __('documents.comparatif.commercial_terms') }}</td>
        <td>{{ $notes['conditions_commerciales'] ?? '—' }}</td>
      </tr>
      <tr>
        <td class="notes-label">{{ __('documents.comparatif.production_lead') }}</td>
        <td>{{ $notes['delai_production'] ?? '—' }}</td>
      </tr>
      <tr>
        <td class="notes-label">{{ __('documents.comparatif.payment') }}</td>
        <td>{{ $notes['paiement'] ?? '—' }}</td>
      </tr>
      <tr>
        <td class="notes-label">{{ __('documents.comparatif.customs_delivery') }}</td>
        <td>{{ $notes['douane_livraison'] ?? '—' }}</td>
      </tr>
    </table>
  </div>

</div>

<div class="conclusion">
  <div class="validity">{{ __('documents.conclusion.terms_validity') }}</div>
  <p>{!! nl2br(e($invoice->legal_mentions)) !!}</p>
  <p>{{ __('documents.conclusion.thanks') }}</p>
</div>

<table class="footer-table">
  <tr>
    <td class="company">{{ strtoupper($company->legal_name) }}@if($company->tagline) — {{ $company->tagline }}@endif</td>
    <td class="meta">{{ __('documents.meta.generated_by') }} — {{ $invoice->invoice_number }} — {{ __('documents.meta.comparatif_suffix') }} — {{ __('documents.meta.page') }} <span class="pagenum"></span></td>
  </tr>
</table>

</body>
</html>
