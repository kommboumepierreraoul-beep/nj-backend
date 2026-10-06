<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use App\Enums\ClientStatus;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentDirection;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderPaymentMethod;
use App\Enums\SalesOrderPaymentStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Enums\SupplierReliability;
use App\Enums\TransportMode;
use App\Enums\UserRole;
use App\Enums\ValueSegment;
use App\Enums\VariantLevel;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Blocs "sous les 4 KPI" du tableau de bord (NJ Global Trade Dashboard.dc.html,
 * rangees 2 a 5 : performance mensuelle, qualite du recouvrement, velocite du cycle
 * de vie, 8 panneaux modules, 2 fils d'activite). Alimente GET /dashboard/overview.
 *
 * Choix de conception (Doc/dashboard_overview_addendum.md) : ces blocs sont
 * VOLONTAIREMENT independants du filtre period/date du tableau de bord -- la maquette
 * ne fait reagir au filtre que les cartes "CA et commission", "Taux de transformation"
 * et "Performance par provenance" (servies par DashboardController::stats()). Tout ce
 * qui est ci-dessous est un instantane "maintenant" ou une lecture tout-historique.
 * "net encaisse" et "credite par avoir" reprennent exactement la methode de
 * DashboardController::revenue() pour ne jamais diverger d'une carte a l'autre.
 */
trait BuildsDashboardOverview
{
    private const LIFECYCLE_PAIRS = [
        [SalesOrderStatus::BROUILLON, SalesOrderStatus::PROFORMA_ENVOYEE],
        [SalesOrderStatus::PROFORMA_ENVOYEE, SalesOrderStatus::CONFIRMEE],
        [SalesOrderStatus::CONFIRMEE, SalesOrderStatus::EN_PREPARATION],
        [SalesOrderStatus::EN_PREPARATION, SalesOrderStatus::EXPEDIEE],
        [SalesOrderStatus::EXPEDIEE, SalesOrderStatus::LIVREE],
        [SalesOrderStatus::LIVREE, SalesOrderStatus::CLOTUREE],
    ];

    private function buildOverview(): array
    {
        return [
            'performance_mensuelle' => $this->buildMonthlyPerformance(),
            'qualite_recouvrement' => $this->buildRecoveryQuality(),
            'velocite_cycle_vie' => $this->buildLifecycleVelocity(),
            'panels' => $this->buildPanels(),
            'feeds' => $this->buildFeeds(),
            'relances_count' => $this->relancesCount(),
        ];
    }

    // ---- Performance mensuelle (maquette lignes 501-545 / trendVals()) ----
    // 6 mois glissants jusqu'au mois courant. CA engage = total_amount des commandes
    // non annulees a leur order_date ; net encaisse = ENCAISSEMENT - REMBOURSEMENT a la
    // date du mouvement. Bucketise en PHP (substr) pour rester agnostique du moteur SQL
    // (SQLite en test), meme parti pris que FlowAnalyticsController::averageDurationPerStage.
    private function buildMonthlyPerformance(): array
    {
        $end = Carbon::today()->startOfMonth();
        $start = $end->copy()->subMonths(5);
        $windowFrom = $start->copy()->startOfMonth();
        $windowTo = $end->copy()->endOfMonth();

        $keys = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addMonth()) {
            $keys[] = $cursor->format('Y-m');
        }

        $engagedByMonth = array_fill_keys($keys, 0.0);
        $collectedByMonth = array_fill_keys($keys, 0.0);

        SalesOrder::query()
            ->where('status', '!=', SalesOrderStatus::ANNULEE->value)
            ->whereBetween('order_date', [$windowFrom->toDateString(), $windowTo->toDateString()])
            ->get(['order_date', 'total_amount'])
            ->each(function ($order) use (&$engagedByMonth): void {
                $key = substr((string) $order->order_date, 0, 7);
                if (array_key_exists($key, $engagedByMonth)) {
                    $engagedByMonth[$key] += (float) $order->total_amount;
                }
            });

        DB::table('sales_order_payments')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_payments.sales_order_id')
            ->where('sales_order_payments.is_voided', false)
            ->where('sales_orders.status', '!=', SalesOrderStatus::ANNULEE->value)
            ->whereBetween('sales_order_payments.paid_at', [$windowFrom, $windowTo])
            ->get(['sales_order_payments.paid_at', 'sales_order_payments.amount', 'sales_order_payments.direction'])
            ->each(function ($payment) use (&$collectedByMonth): void {
                $key = substr((string) $payment->paid_at, 0, 7);
                if (! array_key_exists($key, $collectedByMonth)) {
                    return;
                }
                $signed = $payment->direction === PaymentDirection::REMBOURSEMENT->value
                    ? -(float) $payment->amount
                    : (float) $payment->amount;
                $collectedByMonth[$key] += $signed;
            });

        $mois = array_map(fn (string $key) => [
            'mois' => $key,
            'ca_engage' => round($engagedByMonth[$key], 2),
            'net_encaisse' => round($collectedByMonth[$key], 2),
        ], $keys);

        $engage6m = array_sum($engagedByMonth);
        $encaisse6m = array_sum($collectedByMonth);
        $meilleurMois = null;
        $meilleurValeur = 0.0;
        foreach ($engagedByMonth as $key => $value) {
            if ($value > $meilleurValeur) {
                $meilleurValeur = $value;
                $meilleurMois = $key;
            }
        }

        return [
            'mois' => $mois,
            'resume' => [
                'engage_6m' => round($engage6m, 2),
                'encaisse_6m' => round($encaisse6m, 2),
                'meilleur_mois' => $meilleurMois,
                'conversion_caisse_pourcentage' => $engage6m > 0 ? round($encaisse6m / $engage6m * 100, 2) : 0.0,
            ],
        ];
    }

    // ---- Qualite du recouvrement (maquette lignes 547-577 / donutVals()) ----
    // CA engage des commandes vivantes reparti par statut de paiement ; taux recouvre =
    // (net encaisse + credite par avoir) / CA engage, identique a pct(t.net + t.credited,
    // t.revenue) de la maquette.
    private function buildRecoveryQuality(): array
    {
        $liveOrders = SalesOrder::query()->where('status', '!=', SalesOrderStatus::ANNULEE->value);

        $byStatus = (clone $liveOrders)
            ->select('payment_status', DB::raw('COUNT(*) as total'), DB::raw('SUM(total_amount) as montant'))
            ->groupBy('payment_status')
            ->get()
            ->keyBy('payment_status');

        $caEngageTotal = round((float) (clone $liveOrders)->sum('total_amount'), 2);

        $buckets = collect(SalesOrderPaymentStatus::cases())->map(function (SalesOrderPaymentStatus $status) use ($byStatus, $caEngageTotal) {
            $row = $byStatus->get($status->value);
            $montant = round((float) ($row->montant ?? 0), 2);

            return [
                'payment_status' => $status->value,
                'count' => (int) ($row->total ?? 0),
                'montant' => $montant,
                'part_pourcentage' => $caEngageTotal > 0 ? round($montant / $caEngageTotal * 100, 2) : 0.0,
            ];
        })->values()->all();

        $paymentsInRange = fn (string $direction) => DB::table('sales_order_payments')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_payments.sales_order_id')
            ->where('sales_order_payments.is_voided', false)
            ->where('sales_order_payments.direction', $direction)
            ->where('sales_orders.status', '!=', SalesOrderStatus::ANNULEE->value);

        $net = (float) $paymentsInRange(PaymentDirection::ENCAISSEMENT->value)->sum('sales_order_payments.amount')
            - (float) $paymentsInRange(PaymentDirection::REMBOURSEMENT->value)->sum('sales_order_payments.amount');

        $crediteAvoir = (float) DB::table('invoices')
            ->join('sales_orders', 'sales_orders.id', '=', 'invoices.sales_order_id')
            ->where('invoices.document_type', InvoiceDocumentType::AVOIR->value)
            ->where('sales_orders.status', '!=', SalesOrderStatus::ANNULEE->value)
            ->sum('invoices.total_amount');

        return [
            'ca_engage_total' => $caEngageTotal,
            'net_encaisse_total' => round($net, 2),
            'credite_avoir_total' => round($crediteAvoir, 2),
            'taux_recouvre_pourcentage' => $caEngageTotal > 0 ? round(($net + $crediteAvoir) / $caEngageTotal * 100, 2) : 0.0,
            'buckets' => $buckets,
        ];
    }

    // ---- Velocite du cycle de vie (maquette lignes 580-614 / velocityVals()) ----
    // Duree moyenne entre la 1re entree en statut A et la 1re entree en statut B, par
    // paire d'etapes consecutives du cycle de vie, sur tout l'historique (pas de fenetre).
    // Source : sales_order_status_history, deja horodatee par le module Commandes.
    private function buildLifecycleVelocity(): array
    {
        $history = DB::table('sales_order_status_history')
            ->orderBy('sales_order_id')
            ->orderBy('created_at')
            ->get(['sales_order_id', 'to_status', 'created_at'])
            ->groupBy('sales_order_id');

        $firstReachedByOrder = $history->map(function ($rows) {
            $first = [];
            foreach ($rows as $row) {
                if (! isset($first[$row->to_status])) {
                    $first[$row->to_status] = Carbon::parse($row->created_at);
                }
            }

            return $first;
        });

        $etapes = [];
        $total = 0.0;

        foreach (self::LIFECYCLE_PAIRS as [$from, $to]) {
            $spans = [];
            foreach ($firstReachedByOrder as $reached) {
                if (isset($reached[$from->value], $reached[$to->value])) {
                    $days = $reached[$from->value]->diffInHours($reached[$to->value]) / 24;
                    if ($days >= 0) {
                        $spans[] = $days;
                    }
                }
            }

            if ($spans === []) {
                continue;
            }

            $avg = round(array_sum($spans) / count($spans), 1);
            $total += $avg;
            $etapes[] = [
                'from_status' => $from->value,
                'to_status' => $to->value,
                'jours_moyen' => $avg,
                'commandes_count' => count($spans),
            ];
        }

        return [
            'total_jours' => round($total, 1),
            'etapes' => $etapes,
        ];
    }

    // ---- 8 panneaux modules (maquette lignes 616-664 / panelVals()) ----
    // Codes + nombres uniquement : les libelles sont cote frontend (meme parti pris que
    // sales-flow-tab.tsx). Comptages instantanes tout-module.
    private function buildPanels(): array
    {
        return [
            $this->panelPipelineCommercial(),
            $this->panelTresorerie(),
            $this->panelDocumentsEmis(),
            $this->panelPortefeuilleClients(),
            $this->panelCatalogue(),
            $this->panelSourcingFournisseurs(),
            $this->panelAccesTracabilite(),
            $this->panelConfiguration(),
        ];
    }

    private function metric(string $code, int|float|string $valeur, ?string $unite = null, ?string $ton = null): array
    {
        return array_filter([
            'code' => $code,
            'valeur' => $valeur,
            'unite' => $unite,
            'ton' => $ton,
        ], fn ($value) => $value !== null);
    }

    private function panelPipelineCommercial(): array
    {
        $byStatus = SalesOrder::query()
            ->select('status', DB::raw('COUNT(*) as total'), DB::raw('SUM(total_amount) as montant'))
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $total = (int) $byStatus->sum('total');
        $vivantes = (int) $byStatus->reject(fn ($row) => in_array($row->status, [SalesOrderStatus::ANNULEE->value, SalesOrderStatus::CLOTUREE->value], true))->sum('total');
        $cloturees = (int) ($byStatus->get(SalesOrderStatus::CLOTUREE->value)->total ?? 0);
        $annulees = (int) ($byStatus->get(SalesOrderStatus::ANNULEE->value)->total ?? 0);

        $maxCount = max(1, (int) $byStatus->max('total'));
        $barres = collect(SalesOrderStatus::cases())
            ->map(function (SalesOrderStatus $status) use ($byStatus, $maxCount) {
                $row = $byStatus->get($status->value);
                $count = (int) ($row->total ?? 0);

                return [
                    'code' => $status->value,
                    'valeur' => $count,
                    'montant' => round((float) ($row->montant ?? 0), 2),
                    'pourcentage' => round($count / $maxCount * 100, 1),
                ];
            })
            ->filter(fn ($bar) => $bar['valeur'] > 0)
            ->values()
            ->all();

        $byType = SalesOrder::query()
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'code' => 'pipeline_commercial',
            'metriques' => [
                $this->metric('commandes', $total),
                $this->metric('en_cours', $vivantes, null, 'accent'),
                $this->metric('cloturees', $cloturees, null, 'success'),
                $this->metric('annulees', $annulees, null, 'danger'),
            ],
            'barres' => $barres,
            'pied' => collect(SalesOrderType::cases())->mapWithKeys(
                fn (SalesOrderType $type) => [$type->value => (int) ($byType[$type->value] ?? 0)]
            )->all(),
        ];
    }

    private function panelTresorerie(): array
    {
        $movement = fn (string $direction, bool $voided) => DB::table('sales_order_payments')
            ->where('direction', $direction)
            ->where('is_voided', $voided);

        $brut = (float) $movement(PaymentDirection::ENCAISSEMENT->value, false)->sum('amount');
        $rembourse = (float) $movement(PaymentDirection::REMBOURSEMENT->value, false)->sum('amount');
        $actifs = (int) DB::table('sales_order_payments')->where('is_voided', false)->count();
        $annules = (int) DB::table('sales_order_payments')->where('is_voided', true)->count();
        $crediteAvoir = (float) DB::table('invoices')->where('document_type', InvoiceDocumentType::AVOIR->value)->sum('total_amount');

        $byMethod = DB::table('sales_order_payments')
            ->where('is_voided', false)
            ->where('direction', PaymentDirection::ENCAISSEMENT->value)
            ->select('payment_method', DB::raw('SUM(amount) as montant'))
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $maxMethod = max(1.0, (float) $byMethod->max('montant'));
        $barres = collect(SalesOrderPaymentMethod::cases())
            ->map(fn (SalesOrderPaymentMethod $method) => [
                'code' => $method->value,
                'valeur' => round((float) ($byMethod->get($method->value)->montant ?? 0), 2),
                'pourcentage' => round((float) ($byMethod->get($method->value)->montant ?? 0) / $maxMethod * 100, 1),
            ])
            ->filter(fn ($bar) => $bar['valeur'] > 0)
            ->sortByDesc('valeur')
            ->values()
            ->all();

        $byPaymentStatus = SalesOrder::query()
            ->select('payment_status', DB::raw('COUNT(*) as total'))
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status');

        return [
            'code' => 'tresorerie',
            'metriques' => [
                $this->metric('encaisse_brut', round($brut, 2), 'FCFA', 'success'),
                $this->metric('rembourse', round($rembourse, 2), 'FCFA', 'warning'),
                $this->metric('net_encaisse', round($brut - $rembourse, 2), 'FCFA', 'success'),
                $this->metric('credite_avoir', round($crediteAvoir, 2), 'FCFA', 'danger'),
                $this->metric('mouvements_actifs', $actifs),
                $this->metric('mouvements_annules', $annules, null, 'danger'),
            ],
            'barres' => $barres,
            'pied' => collect(SalesOrderPaymentStatus::cases())->mapWithKeys(
                fn (SalesOrderPaymentStatus $status) => [$status->value => (int) ($byPaymentStatus[$status->value] ?? 0)]
            )->all(),
        ];
    }

    private function panelDocumentsEmis(): array
    {
        $byType = DB::table('invoices')
            ->select('document_type', DB::raw('COUNT(*) as total'))
            ->groupBy('document_type')
            ->pluck('total', 'document_type');

        $metriques = collect(InvoiceDocumentType::cases())
            ->map(fn (InvoiceDocumentType $type) => $this->metric($type->value, (int) ($byType[$type->value] ?? 0)))
            ->all();

        $metriques[] = $this->metric('actifs_emise', (int) DB::table('invoices')->where('status', InvoiceStatus::EMISE->value)->count(), null, 'success');
        $metriques[] = $this->metric('remplaces', (int) DB::table('invoices')->where('status', InvoiceStatus::REMPLACEE->value)->count());
        $metriques[] = $this->metric('montant_facture', round((float) DB::table('invoices')->where('document_type', InvoiceDocumentType::FACTURE->value)->sum('total_amount'), 2), 'FCFA');

        return [
            'code' => 'documents_emis',
            'metriques' => $metriques,
            'barres' => [],
            'pied' => null,
        ];
    }

    private function panelPortefeuilleClients(): array
    {
        $byStatus = DB::table('clients')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) DB::table('clients')->count();
        $actifsVip = (int) ($byStatus[ClientStatus::ACTIF->value] ?? 0) + (int) ($byStatus[ClientStatus::VIP->value] ?? 0);
        $entreprises = (int) DB::table('clients')->where('client_type', 'ENTREPRISE')->count();
        $bloques = (int) ($byStatus[ClientStatus::BLOQUE->value] ?? 0);
        $derogatoire = (int) DB::table('clients')->where('has_custom_commission', true)->count();
        $contacts = (int) DB::table('client_contacts')->count();

        $bySegment = DB::table('clients')
            ->select('value_segment', DB::raw('COUNT(*) as total'))
            ->groupBy('value_segment')
            ->pluck('total', 'value_segment');

        $maxSegment = max(1, (int) ($bySegment->max() ?? 0));
        $barres = collect(ValueSegment::cases())
            ->map(fn (ValueSegment $segment) => [
                'code' => $segment->value,
                'valeur' => (int) ($bySegment[$segment->value] ?? 0),
                'pourcentage' => round((int) ($bySegment[$segment->value] ?? 0) / $maxSegment * 100, 1),
            ])
            ->filter(fn ($bar) => $bar['valeur'] > 0)
            ->values()
            ->all();

        return [
            'code' => 'portefeuille_clients',
            'metriques' => [
                $this->metric('clients', $total),
                $this->metric('actifs_vip', $actifsVip, null, 'success'),
                $this->metric('entreprises', $entreprises),
                $this->metric('bloques', $bloques, null, 'danger'),
                $this->metric('commission_derogatoire', $derogatoire, null, 'accent'),
                $this->metric('contacts', $contacts),
            ],
            'barres' => $barres,
            'pied' => null,
        ];
    }

    private function panelCatalogue(): array
    {
        $products = (int) DB::table('products')->count();
        $productsActifs = (int) DB::table('products')->where('status', 'ACTIVE')->count();
        $productsSensibles = (int) DB::table('products')->where('is_sensitive', true)->count();
        $variants = (int) DB::table('product_variants')->count();
        $variantsActives = (int) DB::table('product_variants')->where('is_active', true)->count();
        $margeMoyenne = (float) DB::table('product_variants')->whereNotNull('margin_rate')->avg('margin_rate');

        $byLevel = DB::table('product_variants')
            ->select('level', DB::raw('COUNT(*) as total'))
            ->groupBy('level')
            ->pluck('total', 'level');

        $maxLevel = max(1, (int) ($byLevel->max() ?? 0));
        $barres = collect(VariantLevel::cases())
            ->map(fn (VariantLevel $level) => [
                'code' => $level->value,
                'valeur' => (int) ($byLevel[$level->value] ?? 0),
                'pourcentage' => round((int) ($byLevel[$level->value] ?? 0) / $maxLevel * 100, 1),
            ])
            ->filter(fn ($bar) => $bar['valeur'] > 0)
            ->values()
            ->all();

        return [
            'code' => 'catalogue',
            'metriques' => [
                $this->metric('produits', $products),
                $this->metric('produits_actifs', $productsActifs, null, 'success'),
                $this->metric('variantes', $variants),
                $this->metric('variantes_actives', $variantsActives, null, 'success'),
                $this->metric('produits_sensibles', $productsSensibles, null, 'danger'),
                $this->metric('marge_moyenne', round($margeMoyenne, 1), '%', 'accent'),
            ],
            'barres' => $barres,
            'pied' => [
                'categories' => (int) DB::table('product_categories')->count(),
                'attributs' => (int) DB::table('product_attributes')->count(),
                'tags' => (int) DB::table('tags')->count(),
            ],
        ];
    }

    private function panelSourcingFournisseurs(): array
    {
        $suppliers = (int) DB::table('suppliers')->count();
        $actifs = (int) DB::table('suppliers')->where('is_active', true)->where('is_blacklisted', false)->count();
        $blacklist = (int) DB::table('suppliers')->where('is_blacklisted', true)->count();
        $rfqs = (int) DB::table('rfqs')->count();
        $pos = (int) DB::table('purchase_orders')->count();
        $engage = (float) DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->where('purchase_orders.status', '!=', PurchaseOrderStatus::CANCELLED->value)
            ->sum('purchase_order_items.subtotal');

        $byReliability = DB::table('suppliers')
            ->select('reliability', DB::raw('COUNT(*) as total'))
            ->groupBy('reliability')
            ->pluck('total', 'reliability');

        $maxReliability = max(1, (int) ($byReliability->max() ?? 0));
        $barres = collect(SupplierReliability::cases())
            ->map(fn (SupplierReliability $reliability) => [
                'code' => $reliability->value,
                'valeur' => (int) ($byReliability[$reliability->value] ?? 0),
                'pourcentage' => round((int) ($byReliability[$reliability->value] ?? 0) / $maxReliability * 100, 1),
            ])
            ->filter(fn ($bar) => $bar['valeur'] > 0)
            ->values()
            ->all();

        return [
            'code' => 'sourcing_fournisseurs',
            'metriques' => [
                $this->metric('fournisseurs', $suppliers),
                $this->metric('actifs', $actifs, null, 'success'),
                $this->metric('liste_noire', $blacklist, null, 'danger'),
                $this->metric('demandes_prix', $rfqs),
                $this->metric('commandes_achat', $pos),
                $this->metric('engage_fournisseurs', round($engage, 2)),
            ],
            'barres' => $barres,
            'pied' => null,
        ];
    }

    private function panelAccesTracabilite(): array
    {
        return [
            'code' => 'acces_tracabilite',
            'metriques' => [
                $this->metric('utilisateurs', (int) DB::table('users')->count()),
                $this->metric('actifs', (int) DB::table('users')->where('is_active', true)->count(), null, 'success'),
                $this->metric('super_admin', (int) DB::table('users')->where('role', UserRole::SUPER_ADMIN->value)->count(), null, 'accent'),
                $this->metric('mot_de_passe_a_changer', (int) DB::table('users')->where('must_change_password', true)->count(), null, 'danger'),
                $this->metric('permissions', (int) DB::table('permissions')->count()),
                $this->metric('evenements_audit', (int) DB::table('audit_logs')->count()),
            ],
            'barres' => [],
            'pied' => null,
        ];
    }

    private function panelConfiguration(): array
    {
        return [
            'code' => 'configuration',
            'metriques' => [
                $this->metric('paliers_commission', (int) DB::table('commission_rules')->where('is_active', true)->count(), null, 'accent'),
                $this->metric('tarifs_transport', (int) DB::table('shipping_rates')->where('is_active', true)->count()),
                $this->metric('devises', (int) DB::table('currencies')->count()),
                $this->metric('modes_transport', count(TransportMode::cases())),
                $this->metric('unites_mesure', (int) DB::table('units_of_measure')->count()),
                $this->metric('provenances_client', (int) DB::table('client_categories')->count()),
            ],
            'barres' => [],
            'pied' => null,
        ];
    }

    // ---- 2 fils d'activite (maquette lignes 666-695 / feedVals()) ----
    private function buildFeeds(): array
    {
        $mouvements = SalesOrderPayment::query()
            ->with(['salesOrder.client', 'currency'])
            ->orderByDesc('paid_at')
            ->limit(5)
            ->get()
            ->map(fn (SalesOrderPayment $payment) => [
                'receipt_number' => $payment->receipt_number,
                'client_full_name' => $payment->salesOrder?->client?->full_name,
                'payment_method' => $payment->payment_method?->value,
                'direction' => $payment->direction?->value,
                'amount' => round((float) $payment->amount, 2),
                'currency' => $payment->currency?->code,
                'paid_at' => $payment->paid_at,
                'is_voided' => (bool) $payment->is_voided,
                'sales_order_id' => $payment->sales_order_id,
            ])
            ->all();

        $documents = Invoice::query()
            ->with(['salesOrder.client', 'currency'])
            ->orderByDesc('issued_at')
            ->limit(5)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'invoice_number' => $invoice->invoice_number,
                'client_full_name' => $invoice->salesOrder?->client?->full_name ?? $invoice->client_name,
                'document_type' => $invoice->document_type?->value,
                'version' => $invoice->version,
                'status' => $invoice->status?->value,
                'total_amount' => round((float) $invoice->total_amount, 2),
                'currency' => $invoice->currency?->code,
                'issued_at' => $invoice->issued_at,
                'sales_order_id' => $invoice->sales_order_id,
            ])
            ->all();

        return [
            'derniers_mouvements' => $mouvements,
            'derniers_documents' => $documents,
        ];
    }

    // Badge d'en-tete "X relance(s)" (maquette ligne 1543 / toChase()) : commandes
    // vivantes, non soldees, dont la validite est depassee ou tombe sous 3 jours.
    private function relancesCount(): int
    {
        $limite = Carbon::today()->addDays(3);

        return (int) SalesOrder::query()
            ->where('status', '!=', SalesOrderStatus::ANNULEE->value)
            ->where('status', '!=', SalesOrderStatus::CLOTUREE->value)
            ->where('payment_status', '!=', SalesOrderPaymentStatus::PAYEE->value)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<=', $limite->toDateString())
            ->count();
    }
}
