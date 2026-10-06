<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<title>Facture {{ $invoice->invoice_number }}</title>
<style>
  {{--
    Gabarit dompdf (Doc/factures_modele_donnees.md, section 9) : derive de
    resources/views/pdf/invoice_proforma.blade.php (memes contraintes dompdf — pas de
    flexbox/grid, mise en page table/float, police DejaVu Sans pour "≈"). Deux
    differences volontaires : bandeau/tampon "PAYE INTEGRALEMENT" ajoute, et section
    "Moyens de paiement acceptes" retiree (document emis apres reglement complet, cette
    information n'a plus lieu d'etre).
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
  .paid-stamp {
    display: inline-block;
    margin-top: 8px;
    padding: 4px 14px;
    border: 2px solid #1a7a34;
    border-radius: 4px;
    color: #1a7a34;
    font-weight: 800;
    font-size: 12px;
    letter-spacing: 0.8px;
    text-transform: uppercase;
  }

  .band-table { background: #F7CA0F; }
  .band-table td { padding: 10px 32px; font-size: 12px; color: #1a1a1a; vertical-align: middle; }
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

  table.transport-box {
    background: #FAFAFA;
    border: 1px solid #e5e5e5;
    border-radius: 6px;
  }
  table.transport-box td { padding: 12px 14px; vertical-align: top; }
  .transport-item .label {
    font-size: 9.5px;
    text-transform: uppercase;
    color: #8a8a8a;
    letter-spacing: 0.4px;
    margin-bottom: 2px;
  }
  .transport-item .value { font-size: 12.5px; font-weight: 700; }
  .transport-note {
    font-size: 10px;
    color: #8a8a8a;
    font-style: italic;
    margin-top: 8px;
  }

  table.summary { width: 320px; float: right; }
  table.summary td { padding: 5px 0; font-size: 12px; border-bottom: 1px solid #eee; }
  table.summary td.value { text-align: right; }
  table.summary tr.commission td { color: #444; }
  table.summary tr.total td {
    border-bottom: none;
    border-top: 2px solid #111;
    padding-top: 8px;
    font-size: 17px;
    font-weight: 800;
  }
  .summary-equiv {
    text-align: right;
    font-size: 10px;
    font-style: italic;
    color: #8a8a8a;
    margin-top: 3px;
    clear: both;
  }
  .currency-block {
    margin-top: 14px;
    padding-top: 10px;
    border-top: 1px dashed #ccc;
  }
  .currency-block .currency-block-label {
    font-size: 9px;
    letter-spacing: 0.6px;
    text-transform: uppercase;
    color: #999;
    margin-bottom: 6px;
    text-align: right;
  }
  table.currency-row-table td { padding: 4px 0; vertical-align: baseline; }
  .ccy-code { font-size: 11px; font-weight: 700; color: #333; }
  .ccy-code.native { color: #b4890a; }
  .ccy-amount { font-size: 14px; font-weight: 700; color: #111; text-align: right; }
  .ccy-rate { font-size: 9px; color: #999; font-weight: 400; }
  .clearfix { clear: both; }

  .conclusion {
    background: #EAF7EE;
    border: 1px solid #1a7a34;
    border-radius: 8px;
    padding: 16px 20px;
    margin: 22px 32px 0;
    font-size: 11.5px;
  }
  .conclusion strong { color: #145c28; }
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
  $transportLabels = __('documents.transport');
  $transportDelay = __('documents.transport.delay');
  $billingModeLabels = __('documents.billing_mode');
  $transportModeValue = $invoice->transport_mode?->value;
  $salesOrder = $invoice->salesOrder;
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
      <p class="doc-title">{{ __('documents.invoice.title') }}</p>
      <p class="doc-sub">{{ __('documents.invoice.subtitle') }}</p>
      <div class="paid-stamp">{{ __('documents.stamp.paid_in_full') }}</div>
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
          @if($invoice->billing_mode)
            <p>{{ __('documents.common.billing_mode') }} : {{ $billingModeLabels[$invoice->billing_mode->value] ?? $invoice->billing_mode->value }}</p>
          @endif
        </div>
      </td>
    </tr>
  </table>

  <div class="section">
    <div class="section-title">{{ __('documents.section.order_detail') }}</div>
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
          <td colspan="3">{{ __('documents.items.goods_subtotal') }}</td>
          <td class="num">{{ $formatMoney($invoice->subtotal_amount, $invoice->currency->code) }}</td>
        </tr>
      </tfoot>
    </table>
  </div>

  @if($transportModeValue && $transportModeValue !== 'NON_APPLICABLE')
    <div class="section">
      <div class="section-title">{{ __('documents.section.transport') }}</div>
      <table class="transport-box">
        <tr>
          <td class="transport-item">
            <div class="label">{{ __('documents.transport.mode') }}</div>
            <div class="value">{{ $transportLabels[$transportModeValue] ?? $transportModeValue }}</div>
          </td>
          @if($salesOrder?->estimated_weight_kg)
            <td class="transport-item">
              <div class="label">{{ __('documents.transport.est_weight') }}</div>
              <div class="value">≈ {{ rtrim(rtrim(number_format((float) $salesOrder->estimated_weight_kg, 2, ',', ' '), '0'), ',') }} kg</div>
            </td>
          @endif
          @if($salesOrder?->estimated_volume_cbm)
            <td class="transport-item">
              <div class="label">{{ __('documents.transport.est_volume') }}</div>
              <div class="value">≈ {{ rtrim(rtrim(number_format((float) $salesOrder->estimated_volume_cbm, 4, ',', ' '), '0'), ',') }} CBM</div>
            </td>
          @endif
          @if($transportDelay[$transportModeValue] ?? null)
            <td class="transport-item">
              <div class="label">{{ __('documents.transport.lead_time') }}</div>
              <div class="value">{{ $transportDelay[$transportModeValue] }}</div>
            </td>
          @endif
        </tr>
      </table>
      <div class="transport-note">{{ __('documents.transport.note') }}</div>
    </div>
  @endif

  <div class="section">
    <div class="section-title">{{ __('documents.section.financial_summary') }}</div>
    <table class="summary">
      <tr>
        <td>{{ __('documents.items.goods_subtotal') }}</td>
        <td class="value">{{ $formatMoney($invoice->subtotal_amount, $invoice->currency->code) }}</td>
      </tr>
      <tr>
        <td>{{ __('documents.summary.discount') }}</td>
        <td class="value">{{ $formatMoney($invoice->discount_amount, $invoice->currency->code) }}</td>
      </tr>
      @if($invoice->commission_amount !== null)
        <tr class="commission">
          <td>{{ __('documents.summary.commission') }}</td>
          <td class="value">{{ $formatMoney($invoice->commission_amount, $invoice->currency->code) }}</td>
        </tr>
      @endif
      @if($invoice->tax_amount !== null && (float) $invoice->tax_amount > 0)
        <tr class="commission">
          <td>{{ __('documents.summary.vat') }} @if($invoice->tax_rate)({{ rtrim(rtrim(number_format((float) $invoice->tax_rate, 2, ',', ' '), '0'), ',') }} %)@endif</td>
          <td class="value">{{ $formatMoney($invoice->tax_amount, $invoice->currency->code) }}</td>
        </tr>
      @endif
      <tr class="total">
        <td>{{ ($invoice->tax_amount ?? 0) > 0 ? __('documents.summary.total_paid_incl_tax') : __('documents.summary.total_paid') }}</td>
        <td class="value">{{ $formatMoney($invoice->total_amount, $invoice->currency->code) }}</td>
      </tr>
    </table>
    <div class="clearfix"></div>

    <table class="summary" style="float: right;">
      <tr><td colspan="2" style="border: none; padding: 0;">
        <div class="currency-block">
          <div class="currency-block-label">{{ __('documents.currency.multi_label') }}</div>
          <table class="currency-row-table">
            <tr>
              <td><span class="ccy-code native">{{ $invoice->currency->code }} <span class="ccy-rate">({{ __('documents.currency.reference') }})</span></span></td>
              <td class="ccy-amount">{{ $formatMoney($invoice->total_amount, $invoice->currency->code) }}</td>
            </tr>
            @foreach($invoice->currency_equivalents ?? [] as $equivalent)
              <tr>
                <td><span class="ccy-code">{{ $equivalent['code'] }} <span class="ccy-rate">1 {{ $equivalent['code'] }} ≈ {{ number_format((float) $equivalent['rate_to_xaf'], 0, ',', ' ') }} XAF</span></span></td>
                <td class="ccy-amount">≈ {{ number_format((float) $equivalent['amount'], 0, ',', ' ') }} {{ $equivalent['code'] }}</td>
              </tr>
            @endforeach
          </table>
        </div>
      </td></tr>
    </table>
    <div class="clearfix"></div>
    <div class="summary-equiv">{{ __('documents.currency.equiv_note_paid', ['code' => $invoice->currency->code]) }}</div>
  </div>

</div>

<div class="conclusion">
  <div class="validity">{{ __('documents.conclusion.settlement_receipt') }}</div>
  <p>{!! nl2br(e($invoice->legal_mentions)) !!}</p>
  <p>{{ __('documents.conclusion.thanks') }}</p>
</div>

<table class="footer-table">
  <tr>
    <td class="company">{{ strtoupper($company->legal_name) }}@if($company->tagline) — {{ $company->tagline }}@endif</td>
    <td class="meta">{{ __('documents.meta.generated_by') }} — {{ $invoice->invoice_number }} — {{ __('documents.meta.page') }} <span class="pagenum"></span></td>
  </tr>
</table>

</body>
</html>
