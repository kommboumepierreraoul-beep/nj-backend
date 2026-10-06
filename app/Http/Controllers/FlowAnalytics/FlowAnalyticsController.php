<?php

namespace App\Http\Controllers\FlowAnalytics;

use App\Enums\DashboardPeriod;
use App\Enums\FlowType;
use App\Enums\PaymentDirection;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RFQStatus;
use App\Enums\RfqSupplierStatus;
use App\Enums\SalesOrderPaymentStatus;
use App\Enums\SalesOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FlowAnalytics\Concerns\ExportsFlowReport;
use App\Models\Currency;
use App\Models\ExchangeRateHistory;
use App\Models\FlowStageThreshold;
use App\Models\ProductSupplier;
use App\Models\PurchaseOrder;
use App\Models\Rfq;
use App\Models\RfqSupplier;
use App\Models\SalesOrder;
use App\Models\SystemTrace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

/**
 * Module "Analyse des flux" (Doc/analyse_flux_modele_donnees.md) : indicateurs de parcours
 * -- ou est-ce que ca bloque, combien de temps ca prend par etape, tendance dans le temps --
 * sur le flux achat, le flux vente, un volet financier et un volet activite (perimetre
 * confirme le 2026-08-26). Complete le Journal d'audit (evenement unitaire) et le Dashboard
 * (photo instantanee des 4 KPI §2.3) sans les remplacer.
 *
 * Lecture seule sur les entites metier existantes -- aucune duplication de donnees (§2 du
 * document) hormis purchase_order_status_history et flow_stage_thresholds (decisions
 * §0bis.1 et §0bis.3).
 */
class FlowAnalyticsController extends Controller
{
    use ExportsFlowReport;

    public function purchaseFlow(Request $request): JsonResponse
    {
        [$period, $from, $to] = $this->resolvePeriod($request);

        return response()->json(array_merge(
            ['period' => $period->value, 'range' => ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()]],
            $this->buildPurchaseFlow($from, $to),
        ));
    }

    public function salesFlow(Request $request): JsonResponse
    {
        [$period, $from, $to] = $this->resolvePeriod($request);

        return response()->json(array_merge(
            ['period' => $period->value, 'range' => ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()]],
            $this->buildSalesFlow($from, $to),
        ));
    }

    public function financial(Request $request): JsonResponse
    {
        [$period, $from, $to] = $this->resolvePeriod($request);

        return response()->json(array_merge(
            ['period' => $period->value, 'range' => ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()]],
            $this->buildFinancial($from, $to),
        ));
    }

    public function activityFlow(Request $request): JsonResponse
    {
        [$period, $from, $to] = $this->resolvePeriod($request);

        return response()->json(array_merge(
            ['period' => $period->value, 'range' => ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()]],
            $this->buildActivityFlow($from, $to),
        ));
    }

    public function bottlenecks(Request $request): JsonResponse
    {
        [$period, $from, $to] = $this->resolvePeriod($request);

        return response()->json([
            'period' => $period->value,
            'range' => ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()],
            'goulots' => $this->buildBottlenecks($from, $to),
        ]);
    }

    /**
     * Ajout additif minimal (Doc/notifications_modele_donnees.md, §6) : expose
     * buildBottlenecks() publiquement pour que App\Console\Commands\ScanNotificationAlerts
     * puisse reutiliser la meme detection de goulots que l'endpoint /flow-analytics/bottlenecks
     * sans la dupliquer. Ne change rien au comportement existant de ce controleur.
     */
    public function detectBreaches(Carbon $from, Carbon $to): array
    {
        return $this->buildBottlenecks($from, $to);
    }

    // Export CSV/PDF (Doc/analyse_flux_modele_donnees.md, §3bis, ajoute au perimetre le
    // 2026-08-26) : {flow} = purchase-flow/sales-flow/financial/activity-flow/bottlenecks,
    // meme permission flow_analytics.view que les endpoints de lecture.
    public function export(Request $request, string $flow): JsonResponse|Response
    {
        $validated = $request->validate([
            'format' => ['required', 'in:csv,pdf'],
        ]);

        [$period, $from, $to] = $this->resolvePeriod($request);

        $builders = [
            'purchase-flow' => fn () => $this->buildPurchaseFlow($from, $to),
            'sales-flow' => fn () => $this->buildSalesFlow($from, $to),
            'financial' => fn () => $this->buildFinancial($from, $to),
            'activity-flow' => fn () => $this->buildActivityFlow($from, $to),
            'bottlenecks' => fn () => ['goulots' => $this->buildBottlenecks($from, $to)],
        ];

        if (! isset($builders[$flow])) {
            return response()->json(['message' => 'Flux inconnu.'], Response::HTTP_NOT_FOUND);
        }

        $titles = [
            'purchase-flow' => 'Flux achat',
            'sales-flow' => 'Flux vente',
            'financial' => 'Flux financier',
            'activity-flow' => "Flux d'activite",
            'bottlenecks' => "Goulots d'etranglement",
        ];

        return $this->exportReport($validated['format'], $flow, $titles[$flow], $period, $from, $to, $builders[$flow]());
    }

    // ---- Resolution de la fenetre temporelle (meme convention que DashboardController) ----

    private function resolvePeriod(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['sometimes', new Enum(DashboardPeriod::class)],
            'date' => ['sometimes', 'date'],
        ]);

        $period = isset($validated['period']) ? DashboardPeriod::from($validated['period']) : DashboardPeriod::MONTH;
        $referenceDate = isset($validated['date']) ? Carbon::parse($validated['date']) : Carbon::today();

        [$from, $to] = match ($period) {
            DashboardPeriod::DAY => [$referenceDate->copy()->startOfDay(), $referenceDate->copy()->endOfDay()],
            DashboardPeriod::WEEK => [$referenceDate->copy()->startOfWeek(), $referenceDate->copy()->endOfWeek()],
            DashboardPeriod::MONTH => [$referenceDate->copy()->startOfMonth(), $referenceDate->copy()->endOfMonth()],
        };

        return [$period, $from, $to];
    }

    // ---- Flux achat (Doc/analyse_flux_modele_donnees.md, §1.1) ----

    private function buildPurchaseFlow(Carbon $from, Carbon $to): array
    {
        // Pipeline instantane (independant de la periode, "maintenant") : ou en sont les
        // commandes fournisseurs actuellement, meme logique que
        // DashboardController::pendingSummary().
        $pipelineCounts = PurchaseOrder::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Taux de reponse fournisseur (sollicitations envoyees dans la periode, sur sent_at).
        $solicited = RfqSupplier::query()->whereBetween('sent_at', [$from, $to]);
        $totalSolicited = (clone $solicited)->count();
        $responded = (clone $solicited)->where('status', RfqSupplierStatus::RESPONDED->value)->count();
        $declined = (clone $solicited)->where('status', RfqSupplierStatus::DECLINED->value)->count();
        $expired = (clone $solicited)->where('status', RfqSupplierStatus::EXPIRED->value)->count();

        // Delai moyen de reponse (sent_at -> response_date), en jours pleins (les deux dates
        // sont ramenees a minuit pour neutraliser l'heure de sent_at, qui est un datetime,
        // vs response_date qui est une simple date).
        $avgResponseDays = (clone $solicited)
            ->where('status', RfqSupplierStatus::RESPONDED->value)
            ->whereNotNull('response_date')
            ->get(['sent_at', 'response_date'])
            ->avg(fn ($row) => Carbon::parse($row->sent_at)->startOfDay()->diffInDays(Carbon::parse($row->response_date)->startOfDay()));

        // Performance par fournisseur (10 plus sollicites sur la periode) -- a croiser avec
        // Supplier.reliability_score deja existant cote fiche fournisseur.
        $bySupplier = RfqSupplier::query()
            ->join('suppliers', 'suppliers.id', '=', 'rfq_suppliers.supplier_id')
            ->whereBetween('rfq_suppliers.sent_at', [$from, $to])
            ->groupBy('suppliers.id', 'suppliers.company_name')
            ->selectRaw('suppliers.id as supplier_id, suppliers.company_name, COUNT(*) as sollicitations, SUM(CASE WHEN rfq_suppliers.status = ? THEN 1 ELSE 0 END) as reponses', [RfqSupplierStatus::RESPONDED->value])
            ->orderByDesc('sollicitations')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'supplier_id' => $row->supplier_id,
                'company_name' => $row->company_name,
                'sollicitations' => (int) $row->sollicitations,
                'reponses' => (int) $row->reponses,
                'taux_reponse_pourcentage' => $row->sollicitations > 0 ? round($row->reponses / $row->sollicitations * 100, 2) : 0.0,
            ]);

        // Taux de selection de devis -- voir la limite documentee en bas de methode.
        $quotes = DB::table('rfq_supplier_quotes')->whereBetween('quoted_at', [$from, $to]);
        $totalQuotes = (clone $quotes)->count();
        $selectedQuotes = (clone $quotes)->where('is_selected', true)->count();

        // Cycle commande fournisseur (RECEIVED dans la periode, sur actual_delivery_date).
        $received = PurchaseOrder::query()
            ->where('status', PurchaseOrderStatus::RECEIVED->value)
            ->whereNotNull('actual_delivery_date')
            ->whereBetween('actual_delivery_date', [$from->toDateString(), $to->toDateString()]);

        $totalReceived = (clone $received)->count();
        $avgCycleDays = (clone $received)
            ->get(['order_date', 'actual_delivery_date'])
            ->avg(fn ($row) => Carbon::parse($row->order_date)->diffInDays(Carbon::parse($row->actual_delivery_date)));
        $lateDeliveries = (clone $received)->whereColumn('actual_delivery_date', '>', 'expected_delivery_date')->count();

        // Taux d'annulation/expiration RFQ sur la periode (request_date).
        $rfqsInPeriod = Rfq::query()->whereBetween('request_date', [$from->toDateString(), $to->toDateString()]);
        $totalRfqs = (clone $rfqsInPeriod)->count();
        $rfqsAnnulees = (clone $rfqsInPeriod)->where('status', RFQStatus::ANNULE->value)->count();
        $rfqsExpirees = (clone $rfqsInPeriod)->where('status', RFQStatus::EXPIRE->value)->count();

        // Taux de transformation RFQ -> commande fournisseur (Doc/analyse_flux_modele_donnees.md,
        // §8.4) : desormais calculable grace a purchase_orders.rfq_id (migration
        // 2026_09_03_000001). Nombre de RFQ de la periode ayant abouti a >= 1 commande
        // fournisseur liee, sur le nombre de RFQ de la periode.
        $rfqIdsInPeriod = (clone $rfqsInPeriod)->pluck('id');
        $rfqsConverties = $rfqIdsInPeriod->isNotEmpty()
            ? (int) PurchaseOrder::query()->whereIn('rfq_id', $rfqIdsInPeriod)->distinct()->count('rfq_id')
            : 0;

        // Temps moyen par statut PO (Doc/analyse_flux_modele_donnees.md, decision §0bis.1) :
        // transitions survenues dans la periode, source purchase_order_status_history.
        // L'historique n'existe que depuis la mise en place de ce module (2026-08-26) : les
        // moyennes restent partielles/vides tant que peu de commandes ont transite depuis.
        $avgTimePerStage = $this->averageDurationPerStage('purchase_order_status_history', $from, $to);

        return [
            'pipeline_actuel' => collect(PurchaseOrderStatus::cases())->mapWithKeys(
                fn ($status) => [$status->value => (int) ($pipelineCounts[$status->value] ?? 0)]
            ),
            'reponse_fournisseur' => [
                'sollicitations' => $totalSolicited,
                'reponses' => $responded,
                'refus' => $declined,
                'expirees' => $expired,
                'taux_reponse_pourcentage' => $totalSolicited > 0 ? round($responded / $totalSolicited * 100, 2) : 0.0,
                'delai_moyen_reponse_jours' => $avgResponseDays !== null ? round((float) $avgResponseDays, 1) : null,
            ],
            'performance_par_fournisseur' => $bySupplier,
            'devis' => [
                'total' => $totalQuotes,
                'selectionnes' => $selectedQuotes,
                'taux_selection_pourcentage' => $totalQuotes > 0 ? round($selectedQuotes / $totalQuotes * 100, 2) : 0.0,
            ],
            'cycle_commande_fournisseur' => [
                'commandes_receptionnees' => $totalReceived,
                'delai_moyen_jours' => $avgCycleDays !== null ? round((float) $avgCycleDays, 1) : null,
                'livraisons_en_retard' => $lateDeliveries,
            ],
            'rfq' => [
                'total' => $totalRfqs,
                'annulees' => $rfqsAnnulees,
                'expirees' => $rfqsExpirees,
                'converties_en_commande' => $rfqsConverties,
                'taux_transformation_pourcentage' => $totalRfqs > 0 ? round($rfqsConverties / $totalRfqs * 100, 2) : 0.0,
            ],
            'temps_moyen_par_statut_jours' => $avgTimePerStage,
            // Le taux de transformation RFQ -> commande fournisseur est desormais calcule
            // (rfq.taux_transformation_pourcentage) via purchase_orders.rfq_id (migration
            // 2026_09_03_000001). Limite residuelle : seules les commandes fournisseurs
            // creees APRES cette migration portent le lien -- les taux restent partiels
            // tant que peu de commandes liees existent. "devis.taux_selection_pourcentage"
            // reste expose comme second indicateur.
            'limite' => "Le taux de transformation RFQ -> commande n'est fiable que pour les commandes fournisseurs creees avec le lien rfq_id (depuis 2026-09-03) ; sur l'anterieur, se referer a devis.taux_selection_pourcentage.",
        ];
    }

    // ---- Flux vente (Doc/analyse_flux_modele_donnees.md, §1.2) ----

    private function buildSalesFlow(Carbon $from, Carbon $to): array
    {
        $pipelineCounts = SalesOrder::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Temps moyen par statut (source : sales_order_status_history, horodatee depuis
        // l'origine du module Commandes -- aucune nouvelle table necessaire ici, contrairement
        // au flux achat).
        $avgTimePerStage = $this->averageDurationPerStage('sales_order_status_history', $from, $to);

        // Taux d'abandon par etape : transitions vers ANNULEE dans la periode, ventilees par
        // le statut duquel la commande venait.
        $cancellationsByStage = DB::table('sales_order_status_history')
            ->where('to_status', SalesOrderStatus::ANNULEE->value)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('from_status')
            ->select('from_status', DB::raw('COUNT(*) as total'))
            ->groupBy('from_status')
            ->pluck('total', 'from_status');

        // Taux de transformation detaille : combien de commandes creees dans la periode ont
        // atteint chaque etape, quel que soit leur etat actuel.
        $orderIdsCreatedInPeriod = SalesOrder::query()
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('id');
        $totalCreated = $orderIdsCreatedInPeriod->count();

        $reachedByStage = $totalCreated > 0
            ? DB::table('sales_order_status_history')
                ->whereIn('sales_order_id', $orderIdsCreatedInPeriod)
                ->select('to_status', DB::raw('COUNT(DISTINCT sales_order_id) as total'))
                ->groupBy('to_status')
                ->pluck('total', 'to_status')
            : collect();

        // Delai moyen de paiement : emission de la PROFORMA (version 1) -> solde de la
        // commande, approxime par le dernier encaissement non annule (meme methode que
        // DashboardController::revenue(), "commission_realisee").
        $soldeDates = DB::table('sales_order_payments')
            ->select('sales_order_id', DB::raw('MAX(paid_at) as solde_at'))
            ->where('is_voided', false)
            ->where('direction', PaymentDirection::ENCAISSEMENT->value)
            ->groupBy('sales_order_id');

        $paidOrders = SalesOrder::query()
            ->joinSub($soldeDates, 'solde', fn ($join) => $join->on('sales_orders.id', '=', 'solde.sales_order_id'))
            ->join('invoices', function ($join) {
                $join->on('invoices.sales_order_id', '=', 'sales_orders.id')
                    ->where('invoices.document_type', 'PROFORMA')
                    ->where('invoices.version', 1);
            })
            ->where('sales_orders.payment_status', SalesOrderPaymentStatus::PAYEE->value)
            ->whereBetween('solde.solde_at', [$from, $to])
            ->select('invoices.issued_at', 'solde.solde_at')
            ->get();

        $avgPaymentDelayDays = $paidOrders->isNotEmpty()
            ? round($paidOrders->avg(fn ($row) => Carbon::parse($row->issued_at)->diffInHours(Carbon::parse($row->solde_at)) / 24), 1)
            : null;

        return [
            'pipeline_actuel' => collect(SalesOrderStatus::cases())->mapWithKeys(
                fn ($status) => [$status->value => (int) ($pipelineCounts[$status->value] ?? 0)]
            ),
            'temps_moyen_par_statut_jours' => $avgTimePerStage,
            'abandons_par_etape' => $cancellationsByStage,
            'commandes_creees' => $totalCreated,
            'atteintes_par_etape' => $reachedByStage,
            'delai_moyen_paiement_jours' => $avgPaymentDelayDays,
        ];
    }

    // ---- Flux financier (Doc/analyse_flux_modele_donnees.md, §1.3) ----

    private function buildFinancial(Carbon $from, Carbon $to): array
    {
        // Tresorerie : mouvements non annules dans la periode. A la difference de
        // DashboardController::revenue() (KPI "CA encaisse"), on n'exclut pas ici les
        // commandes annulees -- un remboursement sur une commande depuis annulee reste un
        // mouvement de tresorerie reel a suivre.
        $paymentsInRange = fn (string $direction) => DB::table('sales_order_payments')
            ->where('is_voided', false)
            ->where('direction', $direction)
            ->whereBetween('paid_at', [$from, $to]);

        $encaisse = (float) $paymentsInRange(PaymentDirection::ENCAISSEMENT->value)->sum('amount');
        $rembourse = (float) $paymentsInRange(PaymentDirection::REMBOURSEMENT->value)->sum('amount');

        // Exposition/impaye : instantane "maintenant" (comme Dashboard::pendingSummary()),
        // complete ici par l'age moyen de l'attente.
        $pending = SalesOrder::query()
            ->where('status', '!=', SalesOrderStatus::ANNULEE->value)
            ->where('payment_status', '!=', SalesOrderPaymentStatus::PAYEE->value)
            ->get(['id', 'order_date', 'total_amount']);

        $avgAgeDays = $pending->isNotEmpty()
            ? round($pending->avg(fn ($order) => Carbon::parse($order->order_date)->diffInDays(Carbon::today())), 1)
            : null;

        // Commission realisee (meme methode que DashboardController::revenue()) vs
        // commission theorique sur les commandes creees dans la periode (payees ou non) --
        // l'ecart donne une lecture de ce qui reste a convertir.
        $soldeDates = DB::table('sales_order_payments')
            ->select('sales_order_id', DB::raw('MAX(paid_at) as solde_at'))
            ->where('is_voided', false)
            ->where('direction', PaymentDirection::ENCAISSEMENT->value)
            ->groupBy('sales_order_id');

        $commissionRealisee = (float) SalesOrder::query()
            ->joinSub($soldeDates, 'solde', fn ($join) => $join->on('sales_orders.id', '=', 'solde.sales_order_id'))
            ->where('sales_orders.payment_status', SalesOrderPaymentStatus::PAYEE->value)
            ->where('sales_orders.status', '!=', SalesOrderStatus::ANNULEE->value)
            ->whereBetween('solde.solde_at', [$from, $to])
            ->sum('sales_orders.commission_amount');

        $commissionTheorique = (float) SalesOrder::query()
            ->where('status', '!=', SalesOrderStatus::ANNULEE->value)
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()])
            ->sum('commission_amount');

        // Avoirs : volume/montant emis dans la periode.
        $creditNotes = DB::table('invoices')
            ->where('document_type', 'AVOIR')
            ->whereBetween('issued_at', [$from, $to]);

        return [
            'tresorerie' => [
                'encaisse' => round($encaisse, 2),
                'rembourse' => round($rembourse, 2),
                'net' => round($encaisse - $rembourse, 2),
            ],
            'exposition' => [
                'commandes_en_attente' => $pending->count(),
                'montant_en_attente' => round((float) $pending->sum('total_amount'), 2),
                'age_moyen_jours' => $avgAgeDays,
            ],
            'commission' => [
                'realisee' => round($commissionRealisee, 2),
                'theorique_sur_commandes_creees' => round($commissionTheorique, 2),
            ],
            'avoirs' => [
                'count' => (clone $creditNotes)->count(),
                'montant_total' => round((float) (clone $creditNotes)->sum('total_amount'), 2),
            ],
            // Marge / rentabilite ESTIMEE (Doc/analyse_flux_modele_donnees.md, decision
            // §0bis.2 : d'abord reportee en phase 2, faute de rapprochement ligne a ligne
            // PO <-> commande client fiable ; livree ici en version estimee, clairement
            // etiquetee comme telle). Cout = dernier prix connu du fournisseur prefere
            // (product_supplier), converti en XAF.
            'marge_estimee' => $this->estimatedMargin($from, $to),
        ];
    }

    /**
     * Marge estimee des commandes soldees (PAYEE, non annulees) dont le dernier
     * encaissement est tombe dans la periode. Pour chaque ligne produit :
     *   cout ligne = quantite x prix unitaire du fournisseur prefere (product_supplier,
     *   repli sur le plus recemment cote), converti en XAF au taux ExchangeRateHistory
     *   le plus recent <= date de la commande (repli : taux le plus recent connu).
     * Les lignes de prestation de service (sans product_variant_id) et les options de
     * proforma non retenues sont exclues. Une ligne sans prix fournisseur connu est
     * comptee dans "lignes_sans_cout_estime" et exclue du cout.
     *
     * C'est une approximation : elle ne reflete pas le prix reellement paye sur la
     * commande fournisseur liee, seulement le dernier tarif catalogue du fournisseur
     * prefere. A remplacer par un calcul exact quand le rapprochement ligne a ligne
     * PO <-> commande client sera systematise (sourced_purchase_order_item_id).
     */
    private function estimatedMargin(Carbon $from, Carbon $to): array
    {
        $methode = 'Estimation sur les commandes en XAF uniquement : CA = sous-totaux des lignes produit ; cout = dernier prix du fournisseur prefere (product_supplier) converti en XAF au taux le plus recent <= date commande. Les commandes libellees dans une autre devise sont exclues (le rapprochement CA/cout ne serait pas homogene). Ne reflete pas le prix reellement paye sur la commande fournisseur liee.';

        $empty = [
            'ca_produits_estime' => 0.0,
            'cout_achat_estime' => 0.0,
            'marge_estimee' => 0.0,
            'taux_marge_pourcentage' => 0.0,
            'commandes_analysees' => 0,
            'lignes_sans_cout_estime' => 0,
            'methode' => $methode,
        ];

        $soldeDates = DB::table('sales_order_payments')
            ->select('sales_order_id', DB::raw('MAX(paid_at) as solde_at'))
            ->where('is_voided', false)
            ->where('direction', PaymentDirection::ENCAISSEMENT->value)
            ->groupBy('sales_order_id');

        $orders = SalesOrder::query()
            ->joinSub($soldeDates, 'solde', fn ($join) => $join->on('sales_orders.id', '=', 'solde.sales_order_id'))
            ->join('currencies', 'currencies.id', '=', 'sales_orders.currency_id')
            ->where('currencies.code', 'XAF')
            ->where('sales_orders.payment_status', SalesOrderPaymentStatus::PAYEE->value)
            ->where('sales_orders.status', '!=', SalesOrderStatus::ANNULEE->value)
            ->whereBetween('solde.solde_at', [$from, $to])
            ->get(['sales_orders.id', 'sales_orders.order_date']);

        if ($orders->isEmpty()) {
            return $empty;
        }

        $orderDateById = $orders->pluck('order_date', 'id');

        $items = DB::table('sales_order_items')
            ->whereIn('sales_order_id', $orders->pluck('id'))
            ->whereNotNull('product_variant_id')
            ->where(fn ($q) => $q->where('is_proposed_option', false)->orWhere('is_selected', true))
            ->get(['sales_order_id', 'product_variant_id', 'quantity', 'subtotal']);

        if ($items->isEmpty()) {
            return array_merge($empty, ['commandes_analysees' => $orders->count()]);
        }

        $variantIds = $items->pluck('product_variant_id')->unique()->values();

        // Prix fournisseur prefere par variante (repli : dernier cote).
        $pricesByVariant = ProductSupplier::query()
            ->whereIn('product_variant_id', $variantIds)
            ->orderByDesc('is_preferred')
            ->orderByDesc('last_quoted_at')
            ->get(['product_variant_id', 'unit_price', 'currency_id'])
            ->groupBy('product_variant_id')
            ->map(fn ($rows) => $rows->first());

        // Taux de change disponibles pour les devises concernees + code des devises.
        $currencyIds = $pricesByVariant->pluck('currency_id')->filter()->unique()->values();
        $currencyCodeById = Currency::query()->whereIn('id', $currencyIds)->pluck('code', 'id');
        $ratesByCurrency = ExchangeRateHistory::query()
            ->whereIn('currency_id', $currencyIds)
            ->orderBy('effective_date')
            ->get(['currency_id', 'rate_to_xaf', 'effective_date'])
            ->groupBy('currency_id');

        $caEstime = 0.0;
        $coutEstime = 0.0;
        $lignesSansCout = 0;

        foreach ($items as $item) {
            $caEstime += (float) $item->subtotal;

            $price = $pricesByVariant->get($item->product_variant_id);
            if (! $price) {
                $lignesSansCout++;

                continue;
            }

            $rate = $this->resolveRateToXaf(
                $currencyCodeById[$price->currency_id] ?? null,
                $ratesByCurrency->get($price->currency_id),
                $orderDateById[$item->sales_order_id] ?? null,
            );

            if ($rate === null) {
                $lignesSansCout++;

                continue;
            }

            $coutEstime += (float) $item->quantity * (float) $price->unit_price * $rate;
        }

        $marge = $caEstime - $coutEstime;

        return [
            'ca_produits_estime' => round($caEstime, 2),
            'cout_achat_estime' => round($coutEstime, 2),
            'marge_estimee' => round($marge, 2),
            'taux_marge_pourcentage' => $caEstime > 0 ? round($marge / $caEstime * 100, 2) : 0.0,
            'commandes_analysees' => $orders->count(),
            'lignes_sans_cout_estime' => $lignesSansCout,
            'methode' => $methode,
        ];
    }

    /**
     * Taux de conversion d'une devise vers le XAF a une date donnee. XAF -> 1.0.
     * Sinon : dernier taux <= date commande, repli sur le taux le plus recent connu.
     * Retourne null si aucune information n'est exploitable.
     *
     * @param  Collection<int, ExchangeRateHistory>|null  $rates
     */
    private function resolveRateToXaf(?string $currencyCode, $rates, ?string $orderDate): ?float
    {
        if ($currencyCode === 'XAF') {
            return 1.0;
        }

        if ($rates === null || $rates->isEmpty()) {
            return null;
        }

        if ($orderDate !== null) {
            $onOrBefore = $rates
                ->filter(fn ($r) => Carbon::parse($r->effective_date)->lte(Carbon::parse($orderDate)))
                ->sortByDesc('effective_date')
                ->first();

            if ($onOrBefore) {
                return (float) $onOrBefore->rate_to_xaf;
            }
        }

        return (float) $rates->sortByDesc('effective_date')->first()->rate_to_xaf;
    }

    // ---- Flux d'activite (Doc/analyse_flux_modele_donnees.md, §1.4, perimetre ajoute le
    // 2026-08-26) : agregation sur audit_logs/system_traces, sans dupliquer ces tables. ----

    private function buildActivityFlow(Carbon $from, Carbon $to): array
    {
        $byModule = DB::table('audit_logs')
            ->whereBetween('created_at', [$from, $to])
            ->select('entity_type', DB::raw('COUNT(*) as total'))
            ->groupBy('entity_type')
            ->orderByDesc('total')
            ->get();

        $byUser = DB::table('audit_logs')
            ->join('users', 'users.id', '=', 'audit_logs.actor_user_id')
            ->whereBetween('audit_logs.created_at', [$from, $to])
            ->select('users.id as user_id', 'users.full_name', DB::raw('COUNT(*) as total'))
            ->groupBy('users.id', 'users.full_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $byAction = DB::table('audit_logs')
            ->whereBetween('created_at', [$from, $to])
            ->select('action', DB::raw('COUNT(*) as total'))
            ->groupBy('action')
            ->orderByDesc('total')
            ->get();

        // Anomalies de connexion (system_traces, evenements auth.* deja cables --
        // Doc/audit_trace_systeme.md). Limite assumee (§1.4 du document) : simple
        // comptage/seuillage, pas de scoring ni de correlation IP/comportement.
        $loginFailed = SystemTrace::query()->where('event', 'auth.login_failed')->whereBetween('created_at', [$from, $to])->count();
        $loginBlocked = SystemTrace::query()->where('event', 'auth.login_blocked')->whereBetween('created_at', [$from, $to])->count();
        $permissionDenied = SystemTrace::query()->where('event', 'auth.permission_denied')->whereBetween('created_at', [$from, $to])->count();
        $roleDenied = SystemTrace::query()->where('event', 'auth.role_denied')->whereBetween('created_at', [$from, $to])->count();

        return [
            'actions_par_module' => $byModule,
            'actions_par_utilisateur' => $byUser,
            'actions_par_type' => $byAction,
            'connexion' => [
                'echecs_connexion' => $loginFailed,
                'comptes_verrouilles' => $loginBlocked,
                'acces_refuses_permission' => $permissionDenied,
                'acces_refuses_role' => $roleDenied,
            ],
        ];
    }

    // ---- Vue transversale des goulots (Doc/analyse_flux_modele_donnees.md, §3) ----

    private function buildBottlenecks(Carbon $from, Carbon $to): array
    {
        $purchaseFlow = $this->buildPurchaseFlow($from, $to);
        $salesFlow = $this->buildSalesFlow($from, $to);
        $activityFlow = $this->buildActivityFlow($from, $to);

        $goulots = [];

        foreach ($purchaseFlow['temps_moyen_par_statut_jours'] as $stage => $avgDays) {
            $threshold = FlowStageThreshold::resolveFor(FlowType::ACHAT, $stage);
            if ($threshold && $avgDays > (float) $threshold->threshold_value) {
                $goulots[] = $this->bottleneckRow(FlowType::ACHAT, $stage, $threshold, $avgDays, 'jours');
            }
        }

        foreach ($salesFlow['temps_moyen_par_statut_jours'] as $stage => $avgDays) {
            $threshold = FlowStageThreshold::resolveFor(FlowType::VENTE, $stage);
            if ($threshold && $avgDays > (float) $threshold->threshold_value) {
                $goulots[] = $this->bottleneckRow(FlowType::VENTE, $stage, $threshold, $avgDays, 'jours');
            }
        }

        $activityCounters = [
            'AUTH_LOGIN_FAILED' => $activityFlow['connexion']['echecs_connexion'],
            'AUTH_PERMISSION_DENIED' => $activityFlow['connexion']['acces_refuses_permission'],
        ];

        foreach ($activityCounters as $code => $value) {
            $threshold = FlowStageThreshold::resolveFor(FlowType::ACTIVITE, $code);
            if ($threshold && $value > (float) $threshold->threshold_value) {
                $goulots[] = $this->bottleneckRow(FlowType::ACTIVITE, $code, $threshold, $value, 'occurrences');
            }
        }

        return collect($goulots)->sortByDesc(fn ($g) => $g['ecart'])->values()->all();
    }

    private function bottleneckRow(FlowType $flowType, string $stageCode, FlowStageThreshold $threshold, float $observed, string $unite): array
    {
        return [
            'flow_type' => $flowType->value,
            'stage_code' => $stageCode,
            'label' => $threshold->label,
            'valeur_observee' => $observed,
            'seuil' => (float) $threshold->threshold_value,
            'ecart' => round($observed - (float) $threshold->threshold_value, 2),
            'unite' => $unite,
        ];
    }

    /**
     * Temps moyen (en jours) passe dans chaque valeur "to_status" avant la transition
     * suivante, calcule en PHP a partir d'une table d'historique de statuts
     * (sales_order_status_history / purchase_order_status_history, meme structure :
     * {entity}_id, from_status, to_status, created_at) -- volontairement sans fonction SQL
     * specifique a un moteur (le projet passe par SQLite en test, potentiellement un autre
     * moteur en production). Seules les etapes dont la transition SUIVANTE a eu lieu dans la
     * fenetre [from, to] sont comptees, pour rester coherent avec la semantique "ce qui
     * s'est passe pendant la periode".
     */
    private function averageDurationPerStage(string $table, Carbon $from, Carbon $to): array
    {
        $foreignKey = $table === 'purchase_order_status_history' ? 'purchase_order_id' : 'sales_order_id';

        $rows = DB::table($table)
            ->orderBy($foreignKey)
            ->orderBy('created_at')
            ->get(['id', $foreignKey, 'to_status', 'created_at']);

        $durationsByStage = [];

        $rows->groupBy($foreignKey)->each(function ($entityRows) use (&$durationsByStage, $from, $to) {
            $entityRows = $entityRows->values();

            for ($i = 0; $i < $entityRows->count() - 1; $i++) {
                $current = $entityRows[$i];
                $next = $entityRows[$i + 1];

                $nextCreatedAt = Carbon::parse($next->created_at);
                if ($nextCreatedAt->lt($from) || $nextCreatedAt->gt($to)) {
                    continue;
                }

                $durationDays = Carbon::parse($current->created_at)->diffInHours($nextCreatedAt) / 24;
                $durationsByStage[$current->to_status][] = $durationDays;
            }
        });

        return collect($durationsByStage)->map(
            fn (array $durations) => round(array_sum($durations) / count($durations), 1)
        )->all();
    }
}
