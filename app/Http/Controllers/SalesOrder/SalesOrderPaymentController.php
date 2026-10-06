<?php

namespace App\Http\Controllers\SalesOrder;

use App\Enums\AttachmentType;
use App\Enums\DocumentLanguage;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentDirection;
use App\Enums\SalesOrderPaymentMethod;
use App\Enums\SalesOrderPaymentStatus;
use App\Http\Controllers\Concerns\LocalizesDocument;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Invoice\Concerns\ComputesCurrencyEquivalents;
use App\Http\Controllers\SalesOrder\Concerns\RecalculatesPaymentStatus;
use App\Http\Resources\SalesOrderPaymentResource;
use App\Models\AuditLog;
use App\Models\CompanyPaymentMethod;
use App\Models\CompanySettings;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderPayment;
use App\Notifications\SalesOrderPaymentReceivedNotification;
use App\Support\NotificationDispatcher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class SalesOrderPaymentController extends Controller
{
    use ComputesCurrencyEquivalents, LocalizesDocument, RecalculatesPaymentStatus;

    public function index(SalesOrder $salesOrder): JsonResponse
    {
        return response()->json([
            // Correctif : 'currency' n'etait jamais eager-charge alors que
            // SalesOrderPaymentResource l'expose desormais via whenLoaded('currency') —
            // sans ce with(), la ressource ne renvoyait aucune cle 'currency' et
            // SalesOrderPaymentsTab (row.currency.code) recevait `undefined`.
            'data' => SalesOrderPaymentResource::collection($salesOrder->payments()->with('currency')->orderByDesc('id')->get()),
        ]);
    }

    // Registre transverse des paiements (Doc/design_system_maquette_complete.md § 6.2 :
    // point de cadrage precedemment laisse ouvert cote frontend faute d'endpoint
    // d'agregation). Agrege les mouvements de tresorerie de toutes les commandes en une
    // seule requete paginee, avec le contexte commande/client necessaire a l'affichage —
    // contrairement a index() ci-dessus, qui liste les mouvements d'une seule commande.
    public function indexGlobal(Request $request): JsonResponse
    {
        $payments = $this->applyPaymentContextFilters(SalesOrderPayment::query(), $request)
            ->when($request->filled('direction'), fn (Builder $query) => $query->where('direction', $request->input('direction')))
            ->when($request->filled('payment_method'), fn (Builder $query) => $query->where('payment_method', $request->input('payment_method')))
            ->when($request->filled('is_voided'), fn (Builder $query) => $query->where('is_voided', $request->boolean('is_voided')))
            ->with(['currency', 'salesOrder:id,reference,client_id', 'salesOrder.client:id,full_name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        // Totaux par devise (jamais toutes devises confondues : additionner du FCFA et de
        // l'EUR sous un seul chiffre produirait un montant denue de sens — la conversion au
        // taux du jour resterait a batir separement si NJ Global Trade la demande un jour).
        // Portent sur les mouvements actifs (non annules) correspondant aux filtres de
        // contexte (client/devise/recherche/periode), mais pas aux filtres d'affichage
        // (sens/methode/statut d'annulation), pour rester un vrai "solde du perimetre
        // consulte" independant du sous-ensemble momentanement affiche.
        $totalsByCurrency = $this->applyPaymentContextFilters(SalesOrderPayment::query(), $request)
            ->where('is_voided', false)
            ->join('currencies', 'currencies.id', '=', 'sales_order_payments.currency_id')
            ->selectRaw(
                'sales_order_payments.currency_id as currency_id, currencies.code as currency_code, '
                .'SUM(CASE WHEN sales_order_payments.direction = ? THEN sales_order_payments.amount ELSE 0 END) as gross_collected, '
                .'SUM(CASE WHEN sales_order_payments.direction = ? THEN sales_order_payments.amount ELSE 0 END) as total_refunded',
                [PaymentDirection::ENCAISSEMENT->value, PaymentDirection::REMBOURSEMENT->value],
            )
            ->groupBy('sales_order_payments.currency_id', 'currencies.code')
            ->get()
            ->map(fn ($row) => [
                'currency_id' => $row->currency_id,
                'currency_code' => $row->currency_code,
                'gross_collected' => (float) $row->gross_collected,
                'total_refunded' => (float) $row->total_refunded,
                'net' => (float) $row->gross_collected - (float) $row->total_refunded,
            ])
            ->values();

        return response()->json([
            'data' => SalesOrderPaymentResource::collection($payments),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'totals_by_currency' => $totalsByCurrency,
            ],
        ]);
    }

    // Filtres de contexte partages entre la liste paginee et les totaux par devise de
    // indexGlobal() ci-dessus (le sens/la methode/le statut d'annulation restent propres a
    // la liste, voir le commentaire sur $totalsByCurrency).
    private function applyPaymentContextFilters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('client_id'), fn (Builder $q) => $q->whereHas('salesOrder', fn (Builder $sq) => $sq->where('client_id', $request->input('client_id'))))
            ->when($request->filled('currency_id'), fn (Builder $q) => $q->where('currency_id', $request->input('currency_id')))
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $search = $request->input('search');
                $q->where(function (Builder $sq) use ($search) {
                    $sq->where('receipt_number', 'like', "%{$search}%")
                        ->orWhere('external_reference', 'like', "%{$search}%")
                        ->orWhereHas('salesOrder', fn (Builder $ssq) => $ssq->where('reference', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('paid_from'), fn (Builder $q) => $q->whereDate('paid_at', '>=', $request->input('paid_from')))
            ->when($request->filled('paid_to'), fn (Builder $q) => $q->whereDate('paid_at', '<=', $request->input('paid_to')));
    }

    public function store(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'payment_method' => ['required', new Enum(SalesOrderPaymentMethod::class)],
            // 'direction' (Doc/factures_modele_donnees.md, section 9) : optionnel, defaut
            // ENCAISSEMENT — retro-compatible avec tous les appels existants qui ne
            // l'envoient pas.
            'direction' => ['sometimes', new Enum(PaymentDirection::class)],
            // 'invoice_id' (Doc/factures_modele_donnees.md, section 10) : optionnel ici —
            // rattachement manuel a un document precis. Pour rattacher systematiquement le
            // paiement a une FACTURE/PROFORMA precise, preferer storeForInvoice() ci-dessous
            // (POST /invoices/{invoice}/payments), qui force ce champ depuis la route plutot
            // que de faire confiance a une valeur saisie au corps de la requete.
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        return $this->createPayment($request, $salesOrder, $validated);
    }

    // Reglement rattache a un document precis (Doc/factures_modele_donnees.md, section 10,
    // ajout 2026-08-21 : "je veux le backend de reglement de factures") — POST
    // /invoices/{invoice}/payments. Meme mecanique que store() (encaissement/remboursement,
    // recalcul de payment_status, emission automatique de la FACTURE), avec en plus la
    // tracabilite explicite "quel document ce paiement est cense regler" (invoice_id,
    // toujours force depuis la route, jamais lu dans le corps de la requete). N'introduit
    // aucun solde par document : le montant du/deja regle reste calcule au niveau de la
    // commande (sales_orders.total_amount/credited_amount), invoice_id n'est qu'un tag de
    // tracabilite — simplification documentee, aucune colonne de solde par facture n'existe.
    public function storeForInvoice(Request $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->document_type === InvoiceDocumentType::AVOIR) {
            return response()->json([
                'message' => "Un paiement ne peut pas etre rattache a un avoir : un avoir credite un montant, il ne se regle pas.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($invoice->document_type === InvoiceDocumentType::RECU) {
            return response()->json([
                'message' => "Un paiement ne peut pas etre rattache a un recu : le recu atteste un reglement deja encaisse (Doc/factures_recu_addendum.md).",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Garde-fou ajoute le 2026-08-25 (absent de la version initiale de cette methode) :
        // meme verification que CreditNoteController::store() pour la meme raison — un
        // document ANNULEE ou REMPLACEE (nouvelle version de proforma deja emise) n'est
        // plus le document actif de la commande, rattacher un paiement dessus produirait
        // une tracabilite trompeuse (un paiement "sur" un document mort). Le paiement en
        // lui-meme reste toujours possible via store() (sales-orders/{id}/payments), qui ne
        // rattache a aucun document precis.
        if (! in_array($invoice->status, [InvoiceStatus::EMISE, InvoiceStatus::ENVOYEE], true)) {
            return response()->json([
                'message' => 'Seul un document actif (emis ou envoye) peut recevoir un paiement.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $salesOrder = SalesOrder::query()->findOrFail($invoice->sales_order_id);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'payment_method' => ['required', new Enum(SalesOrderPaymentMethod::class)],
            'direction' => ['sometimes', new Enum(PaymentDirection::class)],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['invoice_id'] = $invoice->id;

        return $this->createPayment($request, $salesOrder, $validated);
    }

    // Historique des mouvements rattaches specifiquement a ce document (invoice_id), en
    // complement de index() qui liste tous les mouvements de la commande quel que soit le
    // document. Reutilise Invoice::payments() (alias de la relation deja presente sur le
    // modele — refundPayments() — voir le commentaire sur ce modele).
    public function indexForInvoice(Invoice $invoice): JsonResponse
    {
        return response()->json([
            'data' => SalesOrderPaymentResource::collection($invoice->payments()->with('currency')->orderByDesc('id')->get()),
        ]);
    }

    // Coeur commun a store() et storeForInvoice() : validation deja faite par l'appelant,
    // $validated ne contient que des cles fillable sur SalesOrderPayment (voir le modele).
    private function createPayment(Request $request, SalesOrder $salesOrder, array $validated): JsonResponse
    {
        $direction = isset($validated['direction'])
            ? PaymentDirection::from($validated['direction'])
            : PaymentDirection::ENCAISSEMENT;

        $validated['direction'] = $direction->value;
        $validated['recorded_by_user_id'] = $request->user()->id;

        // Verrou transactionnel sur la commande (correctif du 2026-08-25 ; meme pattern que
        // issueFactureAutomatically() ci-dessous et CreditNoteController::store(), qui
        // verrouillaient deja la commande — cette methode-ci, la plus frequemment appelee des
        // trois, ne le faisait pas). Sans ce verrou, le controle du plafond de remboursement
        // ci-dessous est une lecture-puis-decision non protegee : deux remboursements
        // concurrents peuvent chacun lire le meme "net deja encaisse" avant qu'aucun des deux
        // n'ait ecrit sa propre ligne, et donc tous les deux passer le controle alors qu'a eux
        // deux ils depassent le montant reellement disponible. Le verrou serialise les
        // mouvements sur une meme commande : le second remboursement concurrent attend que le
        // premier ait commite (et donc que son montant soit deja compte) avant de lire a son
        // tour le net encaisse.
        $refundExceedsNetCollected = false;

        $payment = DB::transaction(function () use ($salesOrder, $direction, $validated, &$refundExceedsNetCollected) {
            $lockedOrder = SalesOrder::query()->whereKey($salesOrder->id)->lockForUpdate()->firstOrFail();

            if ($direction === PaymentDirection::REMBOURSEMENT) {
                $netEncaisse = (float) $lockedOrder->payments()->where('is_voided', false)->where('direction', PaymentDirection::ENCAISSEMENT->value)->sum('amount')
                    - (float) $lockedOrder->payments()->where('is_voided', false)->where('direction', PaymentDirection::REMBOURSEMENT->value)->sum('amount');

                if ((float) $validated['amount'] > $netEncaisse) {
                    $refundExceedsNetCollected = true;

                    return null;
                }
            }

            // receipt_number = REC-<reference commande> pour un encaissement, REMB-<reference>
            // pour un remboursement (Doc/commandes_modele_donnees.md, section 4 ; extension
            // section 9), genere automatiquement, suffixe si plusieurs mouvements non annules
            // de meme sens existent deja sur la commande — genere sous le meme verrou pour
            // eviter, de la meme maniere, un doublon de suffixe entre deux mouvements
            // concurrents de meme sens.
            return $lockedOrder->payments()->create($validated + [
                'receipt_number' => $this->generateReceiptNumber($lockedOrder, $direction),
            ]);
        });

        if ($refundExceedsNetCollected) {
            return response()->json([
                'message' => 'Le montant du remboursement depasse le montant net deja encaisse sur cette commande.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $previousStatus = $salesOrder->fresh()->payment_status;

        // Le passage a "Paye"/"Partiellement paye" reste automatise cote systeme
        // (cahier des charges §2.2) : payment_status n'est jamais assignable en masse
        // (absent du #[Fillable] de SalesOrder), recalcule ici via forceFill(), meme
        // logique que Supplier::reliability_score (SupplierEvaluationController).
        $newStatus = $this->recalculatePaymentStatus($salesOrder);

        AuditLog::record(
            $direction === PaymentDirection::REMBOURSEMENT ? 'sales_order.refund_recorded' : 'sales_order.payment_recorded',
            $salesOrder,
            $request->user(),
            null,
            $payment->only(['id', 'amount', 'direction', 'invoice_id', 'payment_method', 'receipt_number']),
        );

        // Evenement #4 du module Notifications (Doc/notifications_modele_donnees.md, §5) :
        // uniquement pour un encaissement (un remboursement n'est pas un encaissement a
        // feter) — best-effort, ne bloque jamais la suite (facture automatique ci-dessous).
        if ($direction === PaymentDirection::ENCAISSEMENT) {
            NotificationDispatcher::notifyCreatorOrAdmins(
                $salesOrder->createdBy,
                new SalesOrderPaymentReceivedNotification($payment),
            );
        }

        // Facture automatique a l'encaissement integral (Doc/factures_modele_donnees.md,
        // section 9 ; cahier des charges §2.2 : "le workflow de paiement en un clic genere
        // automatiquement un recu tamponne") : seule une transition NON_PAYEE/
        // PARTIELLEMENT_PAYEE -> PAYEE issue d'un ENCAISSEMENT declenche l'emission. Un
        // remboursement ou un avoir qui ferait tomber effectiveTotal a 0 ne redeclenche
        // jamais ce code (garde deliberee : l'emission automatique reste liee a un
        // encaissement reel, pas a une simple annulation de creance).
        if ($direction === PaymentDirection::ENCAISSEMENT
            && $previousStatus !== SalesOrderPaymentStatus::PAYEE
            && $newStatus === SalesOrderPaymentStatus::PAYEE) {
            // Deux pieces distinctes emises au meme declencheur (Doc/factures_recu_addendum.md) :
            // la FACTURE definitive (creance) puis le RECU tamponne « PAYE » (preuve de
            // reglement, cahier des charges §2.2). Meme mode de defaillance que la FACTURE
            // seule jusqu'ici : le paiement est deja commite, une erreur PDF renvoie 500
            // sans perdre l'encaissement.
            $this->issueFactureAutomatically($salesOrder, $request);
            $this->issueRecuAutomatically($salesOrder, $payment, $request);
        }

        $payment->loadMissing('currency');

        return response()->json([
            'message' => $direction === PaymentDirection::REMBOURSEMENT ? 'Remboursement enregistre.' : 'Encaissement enregistre.',
            'data' => new SalesOrderPaymentResource($payment),
        ], Response::HTTP_CREATED);
    }

    public function void(Request $request, SalesOrder $salesOrder, SalesOrderPayment $salesOrderPayment): JsonResponse
    {
        $validated = $request->validate([
            'voided_reason' => ['required', 'string'],
        ]);

        if ($salesOrderPayment->is_voided) {
            return response()->json(['message' => 'Cet encaissement est deja annule.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $salesOrderPayment->update([
            'is_voided' => true,
            'voided_reason' => $validated['voided_reason'],
            'voided_at' => now(),
        ]);

        $this->recalculatePaymentStatus($salesOrder);

        AuditLog::record(
            'sales_order.payment_voided',
            $salesOrder,
            $request->user(),
            null,
            ['payment_id' => $salesOrderPayment->id, 'reason' => $validated['voided_reason']],
        );

        $salesOrderPayment->loadMissing('currency');

        return response()->json([
            'message' => 'Encaissement annule.',
            'data' => new SalesOrderPaymentResource($salesOrderPayment),
        ]);
    }

    private function generateReceiptNumber(SalesOrder $salesOrder, PaymentDirection $direction): string
    {
        $prefix = $direction === PaymentDirection::REMBOURSEMENT ? 'REMB-' : 'REC-';

        $existingCount = $salesOrder->payments()
            ->where('is_voided', false)
            ->where('direction', $direction->value)
            ->count();

        return $existingCount === 0
            ? $prefix.$salesOrder->reference
            : $prefix.$salesOrder->reference.'-'.($existingCount + 1);
    }

    // Emission automatique du RECU tamponne « PAYE » au passage a PAYEE (cahier des
    // charges NJ Global Trade v2, §2.2 ; Doc/factures_recu_addendum.md). Piece distincte
    // de la FACTURE : elle acte le REGLEMENT (mode, date, montant encaisse) plutot que la
    // creance. Numero = le receipt_number deja genere sur le mouvement d'encaissement
    // declencheur (REC-<reference>), un seul RECU par commande, jamais reemis.
    private function issueRecuAutomatically(SalesOrder $salesOrder, SalesOrderPayment $payment, Request $request): void
    {
        if ($salesOrder->invoices()->where('document_type', InvoiceDocumentType::RECU->value)->exists()) {
            return;
        }

        // Meme garde-fou que la FACTURE : une commande dont aucune ligne n'est
        // is_selected=true (ex. PRODUIT_UNIQUE_MULTI_CHOIX jamais finalise) ne peut pas
        // produire de recu detaille. L'encaissement reste enregistre ; le recu pourra
        // etre reconstitue plus tard si NJ Global Trade le demande (hors perimetre).
        if (! $salesOrder->items()->where('is_selected', true)->exists()) {
            return;
        }

        $companySettings = CompanySettings::current();
        $client = $salesOrder->client()->firstOrFail();
        $actorId = $request->user()->id;

        $recu = DB::transaction(function () use ($salesOrder, $client, $payment, $actorId) {
            $lockedOrder = SalesOrder::query()->whereKey($salesOrder->id)->lockForUpdate()->firstOrFail();

            $recu = Invoice::query()->create([
                'invoice_number' => $payment->receipt_number ?: 'REC-'.$lockedOrder->reference,
                'sales_order_id' => $lockedOrder->id,
                'document_type' => InvoiceDocumentType::RECU->value,
                'version' => 1,
                'status' => InvoiceStatus::EMISE->value,
                'client_id' => $client->id,
                'client_name' => $client->legal_name ?: $client->full_name,
                'client_address' => $this->buildClientAddress($client),
                'currency_id' => $lockedOrder->currency_id,
                'language' => $client->preferred_language?->value ?? 'FR',
                'billing_mode' => $lockedOrder->billing_mode?->value,
                'subtotal_amount' => $lockedOrder->subtotal_amount,
                'discount_amount' => $lockedOrder->discount_amount,
                'commission_amount' => $lockedOrder->commission_amount,
                'tax_rate' => $lockedOrder->tax_rate,
                'tax_amount' => $lockedOrder->tax_amount ?? 0,
                'total_amount' => $lockedOrder->total_amount,
                'transport_mode' => $lockedOrder->transport_mode?->value,
                'legal_mentions' => $this->buildRecuLegalMentions($client->preferred_language ?? DocumentLanguage::FR),
                'due_date' => null,
                'currency_equivalents' => $this->computeCurrencyEquivalents($lockedOrder),
                'issued_at' => now(),
                'issued_by_user_id' => $actorId,
            ]);

            $index = 0;
            foreach ($lockedOrder->items()->where('is_selected', true)->orderBy('sort_order')->get() as $item) {
                $recu->items()->create([
                    'sales_order_item_id' => $item->id,
                    'label' => $item->label ?: ($item->productVariant?->name ?? 'Article'),
                    'description' => $item->description ?: $item->productVariant?->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_amount' => $item->discount_amount,
                    'tax_rate' => $item->tax_rate,
                    'tax_amount' => $item->tax_amount ?? 0,
                    'subtotal' => $item->subtotal,
                    'sort_order' => $index++,
                ]);
            }

            // Tracabilite : le mouvement d'encaissement qui a solde la commande pointe
            // vers le recu qu'il a produit (colonne sales_order_payments.invoice_id,
            // deja existante — Doc/factures_backend_avoir_remboursement_addendum.md §1).
            // Uniquement si aucun rattachement explicite n'existe deja : un paiement
            // enregistre via POST /invoices/{invoice}/payments (storeForInvoice) pointe
            // deja vers le document qu'il regle (proforma/facture), on ne l'ecrase pas.
            if ($payment->invoice_id === null) {
                $payment->forceFill(['invoice_id' => $recu->id])->save();
            }

            return $recu;
        });

        $this->renderAndAttachRecuPdf($recu, $companySettings, $payment, $request);

        AuditLog::record(
            'invoice.issued',
            $recu,
            $request->user(),
            null,
            ['document_type' => InvoiceDocumentType::RECU->value, 'version' => 1, 'invoice_number' => $recu->invoice_number, 'auto_issued' => true],
        );
    }

    private function buildRecuLegalMentions(\BackedEnum|string|null $language = null): string
    {
        return trans('documents.legal.receipt', [], $this->documentLocale($language));
    }

    private function renderAndAttachRecuPdf(Invoice $invoice, CompanySettings $companySettings, SalesOrderPayment $payment, Request $request): void
    {
        $invoice->load(['items', 'client', 'currency', 'salesOrder']);
        $payment->loadMissing('currency');

        $logoBase64 = null;
        if ($companySettings->logo_path && Storage::disk('public')->exists($companySettings->logo_path)) {
            $logoBase64 = 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($companySettings->logo_path));
        }

        $methodValue = $payment->payment_method instanceof SalesOrderPaymentMethod
            ? $payment->payment_method->value
            : (string) $payment->payment_method;

        $pdf = $this->withDocumentLocale($invoice->language, fn () => Pdf::loadView('pdf.invoice_recu', [
            'invoice' => $invoice,
            'company' => $companySettings,
            'logoBase64' => $logoBase64,
            'payment' => $payment,
            'paymentMethodLabel' => trans()->has('documents.payment_method.'.$methodValue)
                ? __('documents.payment_method.'.$methodValue)
                : $methodValue,
        ])->setPaper('a4'));

        $fileName = 'recu-'.str_replace(['/', ' '], '-', $invoice->invoice_number).'.pdf';
        $path = 'attachments/invoice/'.$fileName;

        Storage::disk('public')->put($path, $pdf->output());

        $attachment = $invoice->attachments()->create([
            'attachable_type' => Invoice::class,
            'attachable_id' => $invoice->id,
            'file_name' => $fileName,
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'size_kb' => (int) round(Storage::disk('public')->size($path) / 1024),
            'is_primary' => true,
            'sort_order' => 0,
            'uploaded_by_user_id' => $request->user()->id,
            'uploaded_at' => now(),
        ]);

        $attachment->mediaTypes()->create(['type' => AttachmentType::INVOICE_DOCUMENT->value]);
    }

    // Emission automatique de la FACTURE definitive au passage a PAYEE. Reprend la
    // structure d'emission de ProformaController::store() (non modifie, duplique
    // volontairement ici — voir le commentaire de tete de ComputesCurrencyEquivalents)
    // mais sans mecanisme de reemission/versions multiples : une seule FACTURE par
    // commande (SalesOrder::currentFacture()).
    private function issueFactureAutomatically(SalesOrder $salesOrder, Request $request): void
    {
        if (Invoice::query()->where('sales_order_id', $salesOrder->id)->where('document_type', InvoiceDocumentType::FACTURE->value)->exists()) {
            return;
        }

        $hasSelectedItems = $salesOrder->items()->where('is_selected', true)->exists();
        if (! $hasSelectedItems) {
            // Deviation documentee : une commande dont le client n'a jamais tranche
            // (aucune ligne is_selected=true, ex. PRODUIT_UNIQUE_MULTI_CHOIX non finalise)
            // ne peut pas produire de facture automatique. L'encaissement n'est pas bloque
            // pour autant ; l'emission reste possible plus tard via une action manuelle
            // (hors perimetre de cette iteration).
            return;
        }

        $companySettings = CompanySettings::current();
        $client = $salesOrder->client()->firstOrFail();
        // issued_by_user_id = l'acteur qui vient d'enregistrer l'encaissement declencheur
        // (deviation documentee par rapport a l'hypothese initiale "systeme, null" du
        // document de base — Doc/factures_modele_donnees.md, section 9 — retenue pour la
        // tracabilite).
        $actorId = $request->user()->id;

        $invoice = DB::transaction(function () use ($salesOrder, $client, $companySettings, $actorId) {
            $lockedOrder = SalesOrder::query()->whereKey($salesOrder->id)->lockForUpdate()->firstOrFail();

            $invoice = Invoice::query()->create([
                'invoice_number' => 'FACT-'.$lockedOrder->reference,
                'sales_order_id' => $lockedOrder->id,
                'document_type' => InvoiceDocumentType::FACTURE->value,
                'version' => 1,
                'status' => InvoiceStatus::EMISE->value,
                'client_id' => $client->id,
                'client_name' => $client->legal_name ?: $client->full_name,
                'client_address' => $this->buildClientAddress($client),
                'currency_id' => $lockedOrder->currency_id,
                'language' => $client->preferred_language?->value ?? 'FR',
                'billing_mode' => $lockedOrder->billing_mode?->value,
                'subtotal_amount' => $lockedOrder->subtotal_amount,
                'discount_amount' => $lockedOrder->discount_amount,
                'commission_amount' => $lockedOrder->commission_amount,
                'tax_rate' => $lockedOrder->tax_rate,
                'tax_amount' => $lockedOrder->tax_amount ?? 0,
                'total_amount' => $lockedOrder->total_amount,
                'transport_mode' => $lockedOrder->transport_mode?->value,
                'legal_mentions' => $this->buildFactureLegalMentions($client->preferred_language ?? DocumentLanguage::FR),
                'due_date' => null,
                'currency_equivalents' => $this->computeCurrencyEquivalents($lockedOrder),
                'issued_at' => now(),
                'issued_by_user_id' => $actorId,
            ]);

            $index = 0;
            foreach ($lockedOrder->items()->where('is_selected', true)->orderBy('sort_order')->get() as $item) {
                $invoice->items()->create([
                    'sales_order_item_id' => $item->id,
                    'label' => $item->label ?: ($item->productVariant?->name ?? 'Article'),
                    'description' => $item->description ?: $item->productVariant?->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount_amount' => $item->discount_amount,
                    'tax_rate' => null,
                    'tax_amount' => 0,
                    'subtotal' => $item->subtotal,
                    'sort_order' => $index++,
                ]);
            }

            return $invoice;
        });

        $this->renderAndAttachFacturePdf($invoice, $companySettings, $request);

        AuditLog::record(
            'invoice.issued',
            $invoice,
            $request->user(),
            null,
            ['document_type' => $invoice->document_type->value, 'version' => $invoice->version, 'invoice_number' => $invoice->invoice_number, 'auto_issued' => true],
        );
    }

    private function buildFactureLegalMentions(\BackedEnum|string|null $language = null): string
    {
        return trans('documents.legal.invoice', [], $this->documentLocale($language));
    }

    private function renderAndAttachFacturePdf(Invoice $invoice, CompanySettings $companySettings, Request $request): void
    {
        $invoice->load(['items', 'client', 'currency', 'salesOrder']);

        $paymentMethods = CompanyPaymentMethod::query()
            ->where('is_active', true)
            ->where('show_on_documents', true)
            ->orderBy('sort_order')
            ->get();

        $logoBase64 = null;
        if ($companySettings->logo_path && Storage::disk('public')->exists($companySettings->logo_path)) {
            $logoBase64 = 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($companySettings->logo_path));
        }

        $pdf = $this->withDocumentLocale($invoice->language, fn () => Pdf::loadView('pdf.invoice_facture', [
            'invoice' => $invoice,
            'company' => $companySettings,
            'paymentMethods' => $paymentMethods,
            'logoBase64' => $logoBase64,
        ])->setPaper('a4'));

        $fileName = 'facture-'.str_replace(['/', ' '], '-', $invoice->invoice_number).'.pdf';
        $path = 'attachments/invoice/'.$fileName;

        Storage::disk('public')->put($path, $pdf->output());

        $attachment = $invoice->attachments()->create([
            'attachable_type' => Invoice::class,
            'attachable_id' => $invoice->id,
            'file_name' => $fileName,
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'size_kb' => (int) round(Storage::disk('public')->size($path) / 1024),
            'is_primary' => true,
            'sort_order' => 0,
            'uploaded_by_user_id' => $request->user()->id,
            'uploaded_at' => now(),
        ]);

        $attachment->mediaTypes()->create(['type' => AttachmentType::INVOICE_DOCUMENT->value]);
    }
}
