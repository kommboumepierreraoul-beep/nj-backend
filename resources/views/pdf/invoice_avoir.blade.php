<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<title>Avoir {{ $invoice->invoice_number }}</title>
<style>
  {{--
    Gabarit dompdf (Doc/factures_modele_donnees.md, section 8) : version simplifiee de
    resources/views/pdf/invoice_proforma.blade.php (memes contraintes dompdf), sans les
    sections transport/moyens de paiement qui ne concernent pas un avoir. Bandeau rouge
    plutot que jaune pour distinguer visuellement un document qui credite (et non facture)
    un montant.
  --}}
  {{-- margin-bottom reserve la hauteur du pied de page fixe (repete sur chaque page). --}}
  @page { margin: 0 0 52px; }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    font-family: 'DejaVu Sans', 'Helvetica Neue', Helvetica, Arial, sans-serif;
    color: #1a1a1a;
    font-size: 12.5px;
    line-height: 1.5;
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

  .band-table { background: #C0392B; }
  .band-table td { padding: 10px 32px; font-size: 12px; color: #ffffff; vertical-align: middle; }
  .band-table .ref { font-weight: 800; font-size: 14px; }
  .band-table .dates { text-align: right; }
  .band-table .dates div { line-height: 1.4; }

  .body-wrap { padding: 20px 32px 0; }

  .parties-table { margin-bottom: 18px; }
  .parties-table td { width: 50%; vertical-align: top; padding: 0; }
  .parties-table td.spacer { width: 16px; }
  .party-box {
    border: 1px solid #e5e5e5;
    border-radius: 6px;
    padding: 12px 14px;
  }
  .party-box h3 {
    font-size: 10.5px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #B8960C;
    margin: 0 0 6px;
    border-bottom: 2px solid #F7CA0F;
    padding-bottom: 4px;
  }
  .party-box p { margin: 2px 0; font-size: 11.5px; }
  .party-box .name { font-weight: 700; font-size: 13px; }

  .section-title {
    font-size: 12.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #111;
    margin: 0 0 8px;
    padding-bottom: 5px;
    border-bottom: 2px solid #111;
  }
  .section { margin-bottom: 18px; }

  .credited-note {
    background: #FDEDEC;
    border: 1px solid #C0392B;
    border-radius: 6px;
    padding: 10px 14px;
    font-size: 11.5px;
    color: #7B241C;
    margin-bottom: 18px;
  }

  table.items { font-size: 11.5px; }
  table.items thead th {
    background: #111;
    color: #fff;
    text-align: left;
    padding: 8px 10px;
    font-weight: 600;
    font-size: 10.5px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
  }
  table.items thead th.num, table.items td.num { text-align: right; }
  table.items tbody td {
    padding: 8px 10px;
    border-bottom: 1px solid #eee;
  }
  table.items tbody tr.even { background: #FAFAF7; }
  table.items tfoot td {
    padding: 8px 10px;
    font-weight: 700;
    border-top: 2px solid #111;
  }

  table.summary { width: 320px; float: right; }
  table.summary td { padding: 5px 0; font-size: 12px; border-bottom: 1px solid #eee; }
  table.summary td.value { text-align: right; }
  table.summary tr.total td {
    border-bottom: none;
    border-top: 2px solid #C0392B;
    padding-top: 8px;
    font-size: 17px;
    font-weight: 800;
    color: #C0392B;
  }
  .clearfix { clear: both; }

  .conclusion {
    background: #FDEDEC;
    border: 1px solid #C0392B;
    border-radius: 8px;
    padding: 16px 20px;
    margin: 22px 32px 0;
    font-size: 11.5px;
  }
  .conclusion strong { color: #7B241C; }
  .conclusion .validity {
    font-weight: 800;
    color: #111;
    font-size: 12.5px;
    margin-bottom: 6px;
  }

  {{-- position: fixed => dompdf rend cet element en bas de CHAQUE page ; fond
       sombre comme le pied de la proforma (harmonisation des documents). --}}
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
@endphp

<table class="header-table">
  <tr>
    <td style="width: 40%;">
      @if($logoBase64)
        <img class="logo" src="{{ $logoBase64 }}">
      @endif
    </td>
    <td class="doc-meta" style="width: 60%;">
      <p class="doc-title">{{ __('documents.credit_note.title') }}</p>
      <p class="doc-sub">{{ __('documents.credit_note.subtitle') }}</p>
    </td>
  </tr>
</table>

<table class="band-table">
  <tr>
    <td class="ref">{{ __('documents.common.reference') }} : {{ $invoice->invoice_number }}</td>
    <td class="dates">
      <div>{{ __('documents.common.issue_date') }} : {{ optional($invoice->issued_at)->format('d/m/Y') }}</div>
    </td>
  </tr>
</table>

<div class="body-wrap">

  <table class="parties-table">
    <tr>
      <td>
        <div class="party-box">
          <h3>{{ __('documents.common.issued_by') }}</h3>
          <p class="name">{{ $company->legal_name }}</p>
          <p>{{ $company->address_line }}</p>
          @if($company->representation_line)
            <p>{{ $company->representation_line }}</p>
          @endif
          @if($company->whatsapp)
            <p>WhatsApp : {{ $company->whatsapp }}</p>
          @endif
          @if($company->email)
            <p>Email : {{ $company->email }}</p>
          @endif
        </div>
      </td>
      <td class="spacer"></td>
      <td>
        <div class="party-box">
          <h3>{{ __('documents.common.billed_to') }}</h3>
          <p class="name">{{ $invoice->client_name }}</p>
          @if($invoice->client_address)
            <p>{{ $invoice->client_address }}</p>
          @endif
        </div>
      </td>
    </tr>
  </table>

  @if($invoice->credits)
    <div class="credited-note">
      {{ __('documents.credit_note.credited_line', [
          'number' => $invoice->credits->invoice_number,
          'type' => $invoice->credits->document_type?->value === 'FACTURE' ? __('documents.credit_note.type_facture') : __('documents.credit_note.type_proforma'),
          'date' => optional($invoice->credits->issued_at)->format('d/m/Y'),
      ]) }}
    </div>
  @endif

  <div class="section">
    <div class="section-title">{{ __('documents.section.credit_detail') }}</div>
    <table class="items">
      <thead>
        <tr>
          <th>{{ __('documents.items.designation') }}</th>
          <th class="num">{{ __('documents.items.quantity') }}</th>
          <th class="num">{{ __('documents.items.unit_price') }}</th>
          <th class="num">{{ __('documents.items.subtotal') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($invoice->items as $index => $item)
          <tr class="{{ $index % 2 === 1 ? 'even' : '' }}">
            <td>{{ $item->label }}</td>
            <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', ' '), '0'), ',') }}</td>
            <td class="num">{{ $formatMoney($item->unit_price, $invoice->currency->code) }}</td>
            <td class="num">{{ $formatMoney($item->subtotal, $invoice->currency->code) }}</td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3">{{ __('documents.items.credited_subtotal') }}</td>
          <td class="num">{{ $formatMoney($invoice->subtotal_amount, $invoice->currency->code) }}</td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="section">
    <div class="section-title">{{ __('documents.section.summary') }}</div>
    <table class="summary">
      @if($invoice->tax_amount !== null && (float) $invoice->tax_amount > 0)
        <tr>
          <td>{{ __('documents.summary.credited_subtotal_excl_tax') }}</td>
          <td class="value">{{ $formatMoney($invoice->subtotal_amount, $invoice->currency->code) }}</td>
        </tr>
        <tr>
          <td>{{ __('documents.summary.vat') }} @if($invoice->tax_rate)({{ rtrim(rtrim(number_format((float) $invoice->tax_rate, 2, ',', ' '), '0'), ',') }} %)@endif</td>
          <td class="value">{{ $formatMoney($invoice->tax_amount, $invoice->currency->code) }}</td>
        </tr>
      @endif
      <tr class="total">
        <td>{{ ($invoice->tax_amount ?? 0) > 0 ? __('documents.summary.total_credited_incl_tax') : __('documents.summary.total_credited') }}</td>
        <td class="value">{{ $formatMoney($invoice->total_amount, $invoice->currency->code) }}</td>
      </tr>
    </table>
    <div class="clearfix"></div>
  </div>

</div>

<div class="conclusion">
  <div class="validity">{{ __('documents.conclusion.terms') }}</div>
  <p>{!! nl2br(e($invoice->legal_mentions)) !!}</p>
</div>

<table class="footer-table">
  <tr>
    <td class="company">{{ strtoupper($company->legal_name) }}@if($company->tagline) — {{ $company->tagline }}@endif</td>
    <td class="meta">{{ __('documents.meta.generated_by') }} — {{ $invoice->invoice_number }} — {{ __('documents.meta.page') }} <span class="pagenum"></span></td>
  </tr>
</table>

</body>
</html>
