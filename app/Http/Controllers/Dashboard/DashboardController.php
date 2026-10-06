<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\DashboardPeriod;
use App\Enums\PaymentDirection;
use App\Enums\SalesOrderPaymentStatus;
use App\Enums\SalesOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\Concerns\BuildsDashboardOverview;
use App\Http\Resources\DashboardPendingSalesOrderResource;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;

/**
 * Tableau de bord et KPI temps reel (cahier des charges NJ Global Trade v2, section 2.3) :
 * les 4 indicateurs prioritaires identifies explicitement par le dirigeant — "CA et
 * commission", "Factures en attente", "Taux de transformation", "Performance par
 * provenance" — plus le signal de relance de la section 2.4.
 *
 * La section 2.3 a ete redigee a l'origine pour le prototype Flask/SQLite ("factures")
 * decrit en section 6 du cahier des charges ; elle est relue ici a travers le modele
 * Laravel reellement implemente (Doc/invoice_model.md, "point de cadrage central") :
 * "facture" du cahier des charges = SalesOrder, "Payé" = payment_status PAYEE, "En cours" =
 * payment_status != PAYEE, "Annulé" = status ANNULEE.
 *
 * Decision de conception documentee (le cahier des charges ne tranche pas explicitement ce
 * point) : la section 2.2 precise que le statut "En cours" n'a "Aucun impact sur le CA
 * consolide" et que le passage a "Payé" "met a jour CA/commission" — le CA/commission du
 * KPI 1 est donc calcule ici sur les ENCAISSEMENTS reellement recus dans la periode
 * (sales_order_payments, direction ENCAISSEMENT net des REMBOURSEMENT), pas sur le montant
 * des commandes creees. Un second bloc "commandes_actives" (montant total des commandes non
 * annulees creees dans la periode, y compris non payees) est fourni en complement, pour ne
 * pas perdre la lecture litterale de la section 2.3 ("calculee sur les factures actives
 * (hors annulees)").
 */
class DashboardController extends Controller
{
    use BuildsDashboardOverview;

    // Seuil "approche de l'echeance" (section 2.4 du cahier des charges, qui evoque le
    // signal sans preciser de valeur) : une commande en attente est signalee "PROCHE" si son
    // echeance (valid_until) tombe dans les N jours a venir, "DEPASSEE" si l'echeance est
    // deja passee. Valeur par defaut choisie ici, a ajuster si NJ Global Trade exprime une
    // preference differente — voir aussi App\Http\Resources\DashboardPendingSalesOrderResource,
    // qui reutilise cette meme constante pour ne jamais diverger du decompte ci-dessous.
    public const RELANCE_PROCHE_SEUIL_JOURS = 2;

    public function stats(Request $request): JsonResponse
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

        return response()->json([
            'period' => $period->value,
            'range' => ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()],
            'revenue' => $this->revenue($from, $to),
            'factures_en_attente' => $this->pendingSummary(),
            'taux_transformation' => $this->conversionRate($from, $to),
            'performance_par_provenance' => $this->performanceByProvenance($from, $to),
        ]);
    }

    // Blocs complementaires du tableau de bord (NJ Global Trade Dashboard.dc.html, tout ce
    // qui se trouve SOUS les 4 cartes KPI : performance mensuelle, qualite du recouvrement,
    // velocite du cycle de vie, 8 panneaux modules, 2 fils d'activite). Volontairement sans
    // parametre period/date : ces blocs sont un instantane "maintenant"/tout-historique,
    // seules les 3 cartes basees sur order_date de stats() reagissent au filtre de la page
    // (voir App\Http\Controllers\Dashboard\Concerns\BuildsDashboardOverview et
    // Doc/dashboard_overview_addendum.md).
    public function overview(): JsonResponse
    {
        return response()->json($this->buildOverview());
    }

    // Detail liste du KPI "Factures en attente" (section 2.3), avec le niveau d'alerte de
    // relance (section 2.4) par commande — la vue agregee (juste un compte) reste dans
    // stats() via pendingSummary(), cette methode-ci sert l'ecran/la liste que le dirigeant
    // parcourt pour savoir "qui relancer".
    public function pendingSalesOrders(Request $request): JsonResponse
    {
        $salesOrders = SalesOrder::query()
            ->with(['client.category', 'currency'])
            ->where('status', '!=', SalesOrderStatus::ANNULEE->value)
            ->where('payment_status', '!=', SalesOrderPaymentStatus::PAYEE->value)
            ->orderByRaw('valid_until IS NULL, valid_until ASC')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => DashboardPendingSalesOrderResource::collection($salesOrders),
            'meta' => [
                'current_page' => $salesOrders->currentPage(),
                'last_page' => $salesOrders->lastPage(),
                'total' => $salesOrders->total(),
            ],
        ]);
    }

    // KPI "CA et commission" (section 2.3) : voir la note de tete de classe pour le choix de
    // methodologie (encaissements reels dans la periode, pas montant des commandes creees).
    private function revenue(Carbon $from, Carbon $to): array
    {
        $paymentsInRange = fn (string $direction) => DB::table('sales_order_payments')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_payments.sales_order_id')
            ->where('sales_order_payments.is_voided', false)
            ->where('sales_order_payments.direction', $direction)
            ->where('sales_orders.status', '!=', SalesOrderStatus::ANNULEE->value)
            ->whereBetween('sales_order_payments.paid_at', [$from, $to]);

        $encaisse = (float) $paymentsInRange(PaymentDirection::ENCAISSEMENT->value)->sum('sales_order_payments.amount');
        $rembourse = (float) $paymentsInRange(PaymentDirection::REMBOURSEMENT->value)->sum('sales_order_payments.amount');
        $nombreEncaissements = (int) $paymentsInRange(PaymentDirection::ENCAISSEMENT->value)->count();
        $nombreRemboursements = (int) $paymentsInRange(PaymentDirection::REMBOURSEMENT->value)->count();

        // "Commission realisee" : la commission figee sur une commande (sales_orders.
        // commission_amount) n'est pas ventilee par paiement — elle est comptee integralement
        // au moment ou la commande atteint payment_status=PAYEE. Ce moment n'a pas de colonne
        // horodatee dediee (payment_status est recalcule sans historique, voir
        // App\Http\Controllers\SalesOrder\Concerns\RecalculatesPaymentStatus) : on l'approxime
        // par la date du dernier encaissement non annule de la commande (MAX(paid_at) sur les
        // mouvements ENCAISSEMENT), ce qui est exact dans le cas courant — un seul mouvement
        // solde la commande, ou le dernier mouvement d'une serie partielle est celui qui la
        // solde. Deviation documentee, aucune section du cahier des charges ne detaille ce cas.
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

        $activeOrders = SalesOrder::query()
            ->where('status', '!=', SalesOrderStatus::ANNULEE->value)
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()]);

        return [
            'ca_encaisse' => round($encaisse - $rembourse, 2),
            'commission_realisee' => round($commissionRealisee, 2),
            'nombre_encaissements' => $nombreEncaissements,
            'nombre_remboursements' => $nombreRemboursements,
            // Contexte pipeline (lecture litterale de la section 2.3 : "calculee sur les
            // factures actives (hors annulees)") : commandes creees dans la periode, quel
            // que soit leur etat de paiement — a distinguer de ca_encaisse ci-dessus (argent
            // reellement recu).
            'commandes_actives' => [
                'count' => (clone $activeOrders)->count(),
                // montant_total = TTC (total_amount inclut la TVA, Doc/tva_addendum.md) ;
                // montant_total_ht le retranche pour lire le chiffre d'affaires hors taxe.
                'montant_total' => round((float) (clone $activeOrders)->sum('total_amount'), 2),
                'montant_total_ht' => round(
                    (float) (clone $activeOrders)->sum('total_amount') - (float) (clone $activeOrders)->sum('tax_amount'),
                    2,
                ),
                'tva_totale' => round((float) (clone $activeOrders)->sum('tax_amount'), 2),
                'commission_totale' => round((float) (clone $activeOrders)->sum('commission_amount'), 2),
            ],
        ];
    }

    // KPI "Factures en attente" (section 2.3), signal visuel de relance (section 2.4).
    // Volontairement independant de la periode $from/$to du tableau de bord : c'est un
    // instantane "maintenant" (des factures encore ouvertes aujourd'hui), pas un decompte
    // sur une fenetre passee.
    private function pendingSummary(): array
    {
        $today = Carbon::today();

        $pending = SalesOrder::query()
            ->where('status', '!=', SalesOrderStatus::ANNULEE->value)
            ->where('payment_status', '!=', SalesOrderPaymentStatus::PAYEE->value);

        $enRetard = (clone $pending)->whereNotNull('valid_until')->whereDate('valid_until', '<', $today)->count();
        $procheEcheance = (clone $pending)->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', $today)
            ->whereDate('valid_until', '<=', $today->copy()->addDays(self::RELANCE_PROCHE_SEUIL_JOURS))
            ->count();

        return [
            'count' => (clone $pending)->count(),
            'en_retard' => $enRetard,
            'proche_echeance' => $procheEcheance,
        ];
    }

    // KPI "Taux de transformation" (section 2.3) : "Pourcentage de proformas emis qui
    // aboutissent a un paiement (Payé / Total hors Annulé)". Calcule sur les commandes
    // creees dans la periode (meme cohorte que "commandes_actives" ci-dessus), pour que les
    // 3 KPI bases sur order_date restent coherents entre eux dans une meme reponse.
    private function conversionRate(Carbon $from, Carbon $to): array
    {
        $activeOrders = SalesOrder::query()
            ->where('status', '!=', SalesOrderStatus::ANNULEE->value)
            ->whereBetween('order_date', [$from->toDateString(), $to->toDateString()]);

        $total = (clone $activeOrders)->count();
        $payees = (clone $activeOrders)->where('payment_status', SalesOrderPaymentStatus::PAYEE->value)->count();

        return [
            'commandes_non_annulees' => $total,
            'commandes_payees' => $payees,
            'taux_pourcentage' => $total > 0 ? round($payees / $total * 100, 2) : 0.0,
        ];
    }

    // KPI "Performance par provenance" (section 2.3) : repartition du CA et du nombre de
    // commandes par client_categories (Doc/clients_modele_donnees.md, section 1.1). La
    // provenance Ecom-Rich/Direct/Autre du cahier des charges §2.5 est modelisee comme
    // categorie client (clients.category_id) plutot qu'un champ dedie sur sales_orders — la
    // table existait deja (module Client) mais n'avait jamais ete seedee, voir la migration
    // 2026_08_25_000001_seed_default_client_categories_table.
    private function performanceByProvenance(Carbon $from, Carbon $to): array
    {
        $rows = SalesOrder::query()
            ->join('clients', 'clients.id', '=', 'sales_orders.client_id')
            ->leftJoin('client_categories', 'client_categories.id', '=', 'clients.category_id')
            ->where('sales_orders.status', '!=', SalesOrderStatus::ANNULEE->value)
            ->whereBetween('sales_orders.order_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('client_categories.id', 'client_categories.code', 'client_categories.label')
            ->selectRaw('client_categories.code as category_code, client_categories.label as category_label, COUNT(*) as commandes_count, SUM(sales_orders.total_amount) as ca_total')
            ->orderByDesc('ca_total')
            ->get();

        return $rows->map(fn ($row) => [
            'category_code' => $row->category_code,
            'category_label' => $row->category_label ?? 'Sans categorie',
            'commandes_count' => (int) $row->commandes_count,
            'ca_total' => round((float) $row->ca_total, 2),
        ])->all();
    }
}
