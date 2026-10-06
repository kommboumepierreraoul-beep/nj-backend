<?php

namespace App\Http\Controllers\Invoice;

use App\Enums\AttachmentType;
use App\Enums\CommissionType;
use App\Enums\DocumentLanguage;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Enums\ShippingMode;
use App\Enums\VariantLevel;
use App\Http\Controllers\Concerns\LocalizesDocument;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\CommissionRule;
use App\Models\CompanyPaymentMethod;
use App\Models\CompanySettings;
use App\Models\Currency;
use App\Models\ExchangeRateHistory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\SalesOrder;
use App\Models\ShippingRate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ProformaController extends Controller
{
    use LocalizesDocument;

    // Emission d'une PROFORMA (Doc/proforma_generation_addendum.md) : cree une nouvelle
    // version immuable, genere le PDF, l'attache. Decision revue par l'utilisateur le
    // 2026-08-17 : l'emission NE touche PLUS au statut de la commande (decouple de
    // SalesOrderController::updateStatus(), qui reste le seul point d'entree pour changer
    // le statut) — la 1ere reponse ("transition automatique") a ete corrigee explicitement
    // en "action manuelle separee".
    public function store(Request $request, SalesOrder $salesOrder): JsonResponse
    {
        if (in_array($salesOrder->status, [SalesOrderStatus::ANNULEE, SalesOrderStatus::CLOTUREE], true)) {
            return response()->json([
                'message' => "Impossible d'emettre une proforma pour une commande annulee ou cloturee.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Embranchement gabarit comparatif (Doc/proforma_comparatif_addendum.md,
        // decision n°2) : reserve au type PRODUIT_UNIQUE_MULTI_CHOIX (3 variantes du meme
        // produit). MULTI_PRODUITS/PRESTATION_SERVICE gardent le comportement actuel
        // inchange (garde-fou is_selected=true, gabarit plat), non touche ci-dessous.
        $isComparatif = $salesOrder->type === SalesOrderType::PRODUIT_UNIQUE_MULTI_CHOIX;

        // Garde-fou d'emission (decision n°2, derniere puce) : pour le type comparatif,
        // "au moins une ligne existe" (le client n'a pas encore tranche entre les options
        // presentees) plutot que "au moins une ligne is_selected=true" (garde-fou inchange
        // pour les 2 autres types, deja teste).
        $hasEmittableItems = $isComparatif
            ? $salesOrder->items()->exists()
            : $salesOrder->items()->where('is_selected', true)->exists();

        if (! $hasEmittableItems) {
            return response()->json([
                'message' => $isComparatif
                    ? 'La commande doit comporter au moins une ligne avant l\'emission de la proforma.'
                    : 'La commande doit comporter au moins une ligne selectionnee avant l\'emission de la proforma.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Bloc "Notes/conditions" + points forts/attention/recommandation par option
        // (decision n°5) : saisis librement a l'emission, valides en souplesse (tout est
        // optionnel), jamais requis y compris pour une commande PRODUIT_UNIQUE_MULTI_CHOIX
        // (les champs du wireframe peuvent legitimement rester vides et etre completes
        // plus tard via une reemission).
        $validated = $request->validate([
            'proposal_details' => ['sometimes', 'nullable', 'array'],
            // Langue du document : FR/EN. Absent → préférence du client (défaut FR).
            'language' => ['sometimes', 'nullable', Rule::in([DocumentLanguage::FR->value, DocumentLanguage::EN->value])],
        ]);
        $proposalDetails = $validated['proposal_details'] ?? null;
        $requestedLanguage = $validated['language'] ?? null;

        $companySettings = CompanySettings::current();

        // Arguments de la proforma comparative desormais stockes en base (variantes +
        // parametres societe) et repris automatiquement — la saisie de l'emetteur, quand
        // elle existe, ne fait que surcharger champ par champ (Doc/proforma_comparatif_addendum.md,
        // demande du 2026-09-03 "simplifier les taches de l'equipe").
        if ($isComparatif) {
            $proposalDetails = $this->resolveProposalDetails($salesOrder, $proposalDetails, $companySettings);
        }

        [$invoice, $supersededInvoice] = DB::transaction(function () use ($request, $salesOrder, $companySettings, $isComparatif, $proposalDetails, $requestedLanguage) {
            // Verrou applicatif sur la commande le temps du calcul de version/numerotation,
            // meme pattern que SalesOrderController::generateReference().
            $lockedOrder = SalesOrder::query()->whereKey($salesOrder->id)->lockForUpdate()->firstOrFail();

            $version = Invoice::query()
                ->where('sales_order_id', $lockedOrder->id)
                ->where('document_type', InvoiceDocumentType::PROFORMA->value)
                ->count() + 1;

            $supersededInvoice = null;
            if ($version > 1) {
                $supersededInvoice = $salesOrder->currentProforma();
            }

            $invoiceNumber = $version === 1
                ? $lockedOrder->reference
                : $lockedOrder->reference.'-V'.$version;

            $client = $salesOrder->client()->firstOrFail();
            $documentLanguage = $this->resolveDocumentLanguage($requestedLanguage, $client->preferred_language);
            $dueDate = $lockedOrder->valid_until
                ?? Carbon::now()->addDays((int) $companySettings->default_proforma_validity_days);

            $invoice = Invoice::query()->create([
                'invoice_number' => $invoiceNumber,
                'sales_order_id' => $lockedOrder->id,
                'document_type' => InvoiceDocumentType::PROFORMA->value,
                'version' => $version,
                'status' => InvoiceStatus::EMISE->value,
                'supersedes_invoice_id' => $supersededInvoice?->id,
                'client_id' => $client->id,
                'client_name' => $client->legal_name ?: $client->full_name,
                'client_address' => $this->buildClientAddress($client),
                'currency_id' => $lockedOrder->currency_id,
                'language' => $documentLanguage->value,
                'billing_mode' => $lockedOrder->billing_mode?->value,
                'subtotal_amount' => $lockedOrder->subtotal_amount,
                'discount_amount' => $lockedOrder->discount_amount,
                'commission_amount' => $lockedOrder->commission_amount,
                'tax_rate' => $lockedOrder->tax_rate,
                'tax_amount' => $lockedOrder->tax_amount ?? 0,
                'total_amount' => $lockedOrder->total_amount,
                'transport_mode' => $lockedOrder->transport_mode?->value,
                'legal_mentions' => $this->buildLegalMentions($dueDate, $documentLanguage),
                'due_date' => $dueDate,
                'currency_equivalents' => $this->computeCurrencyEquivalents($lockedOrder),
                'proposal_details' => $proposalDetails,
                'issued_at' => now(),
                'issued_by_user_id' => $request->user()->id,
            ]);

            // Selection des lignes a emettre : toutes les lignes existantes, triees dans
            // l'ordre des colonnes du wireframe (Premier/Deuxieme/Troisieme choix), pour le
            // type comparatif (decision n°2) ; is_selected=true uniquement sinon (inchange).
            $itemsToEmit = $isComparatif
                ? $salesOrder->items()->with('productVariant')->get()
                    ->sortBy(fn ($item) => $this->levelSortIndex($item->productVariant?->level))
                    ->values()
                : $salesOrder->items()->where('is_selected', true)->orderBy('sort_order')->get();

            $index = 0;
            foreach ($itemsToEmit as $item) {
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

            if ($supersededInvoice) {
                $supersededInvoice->update(['status' => InvoiceStatus::REMPLACEE->value]);
            }

            // Pas de transition de statut ici : emettre une proforma reste une action
            // independante du cycle de vie de la commande (voir commentaire de store()).
            // Le passage a PROFORMA_ENVOYEE reste un geste explicite via
            // SalesOrderController::updateStatus().

            return [$invoice, $supersededInvoice];
        });

        $this->renderAndAttachPdf($invoice, $companySettings, $request);

        AuditLog::record(
            'invoice.issued',
            $invoice,
            $request->user(),
            null,
            ['document_type' => $invoice->document_type->value, 'version' => $invoice->version, 'invoice_number' => $invoice->invoice_number],
        );

        if ($supersededInvoice) {
            AuditLog::record(
                'invoice.superseded',
                $supersededInvoice,
                $request->user(),
                ['status' => InvoiceStatus::EMISE->value],
                ['status' => InvoiceStatus::REMPLACEE->value, 'superseded_by_invoice_id' => $invoice->id],
            );
        }

        return response()->json([
            'message' => 'Proforma emise.',
            'data' => new InvoiceResource($invoice->fresh(['items', 'client', 'currency', 'attachments'])),
        ], Response::HTTP_CREATED);
    }

    // Liste des versions de PROFORMA deja emises pour cette commande, la plus recente
    // en premier.
    public function index(SalesOrder $salesOrder): JsonResponse
    {
        $invoices = $salesOrder->invoices()
            ->where('document_type', InvoiceDocumentType::PROFORMA->value)
            ->with(['items', 'attachments'])
            ->orderByDesc('version')
            ->get();

        return response()->json(['data' => InvoiceResource::collection($invoices)]);
    }

    /**
     * Valeurs par defaut du bloc "proposition" d'une proforma comparative, resolues
     * depuis la base : points forts / attention / recommandation par niveau (sur la
     * fiche de chaque variante proposee) et les 4 champs "Notes / conditions" (defauts
     * societe, Parametres -> Entreprise). Consomme par le dialogue d'emission cote
     * frontend pour pre-remplir le formulaire, que l'emetteur peut ensuite ajuster
     * (Doc/proforma_comparatif_addendum.md, demande du 2026-09-03).
     */
    public function proformaDefaults(SalesOrder $salesOrder): JsonResponse
    {
        return response()->json([
            'data' => $this->resolveProposalDetails($salesOrder, null, CompanySettings::current()),
        ]);
    }

    /**
     * Fusionne les valeurs stockees en base (fiches variantes + parametres societe) avec
     * la saisie eventuelle de l'emetteur : un champ present dans `$submitted` (meme vide)
     * gagne — ce qui permet de vider un argument volontairement ; un champ absent retombe
     * sur la valeur stockee. Le resultat est fige dans `invoices.proposal_details` a
     * l'emission (une edition ulterieure d'une variante ne change pas un document deja
     * emis).
     */
    private function resolveProposalDetails(SalesOrder $salesOrder, ?array $submitted, CompanySettings $company): array
    {
        $submitted ??= [];

        $comparableLevels = [
            VariantLevel::PREMIER_CHOIX->value,
            VariantLevel::DEUXIEME_CHOIX->value,
            VariantLevel::TROISIEME_CHOIX->value,
        ];

        $resolved = [];

        foreach ($salesOrder->items()->with('productVariant')->get() as $item) {
            $level = $item->productVariant?->level?->value;
            if (! in_array($level, $comparableLevels, true) || isset($resolved[$level])) {
                continue;
            }

            $variant = $item->productVariant;
            $levelInput = is_array($submitted[$level] ?? null) ? $submitted[$level] : [];

            $resolved[$level] = [
                'points_forts' => $this->pickList($levelInput['points_forts'] ?? null, $variant->proforma_strengths),
                'points_attention' => $this->pickList($levelInput['points_attention'] ?? null, $variant->proforma_weaknesses),
                'recommandation' => $this->pickText($levelInput['recommandation'] ?? null, $variant->proforma_recommendation),
            ];
        }

        $notesInput = is_array($submitted['notes'] ?? null) ? $submitted['notes'] : [];
        $resolved['notes'] = [
            'conditions_commerciales' => $this->pickText($notesInput['conditions_commerciales'] ?? null, $company->default_proforma_conditions),
            'delai_production' => $this->pickText($notesInput['delai_production'] ?? null, $company->default_proforma_production_delay),
            'paiement' => $this->pickText($notesInput['paiement'] ?? null, $company->default_proforma_payment_terms),
            'douane_livraison' => $this->pickText($notesInput['douane_livraison'] ?? null, $company->default_proforma_customs),
        ];

        return $resolved;
    }

    private function pickList(mixed $submitted, mixed $fallback): array
    {
        if (is_array($submitted)) {
            return array_values(array_filter(
                array_map(static fn ($value) => is_string($value) ? trim($value) : $value, $submitted),
                static fn ($value) => $value !== '' && $value !== null,
            ));
        }

        return is_array($fallback) ? array_values($fallback) : [];
    }

    private function pickText(mixed $submitted, mixed $fallback): ?string
    {
        if (is_string($submitted)) {
            $trimmed = trim($submitted);

            return $trimmed === '' ? null : $trimmed;
        }

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }

    private function buildClientAddress($client): ?string
    {
        $parts = array_filter([
            $client->address_line,
            $client->city,
            $client->region,
            $client->country?->name,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    private function buildLegalMentions(?Carbon $dueDate, \BackedEnum|string|null $language = null): string
    {
        $locale = $this->documentLocale($language);
        $dueDateLabel = $dueDate ? Carbon::parse($dueDate)->format('d/m/Y') : null;

        $validitySentence = $dueDateLabel
            ? trans('documents.legal.proforma_validity', ['due' => $dueDateLabel], $locale)
            : '';

        return trans('documents.legal.proforma', ['validity_sentence' => $validitySentence], $locale);
    }

    // Montant global de la commande, converti dans chaque devise active (autre que la
    // devise de la commande) pour laquelle un taux ExchangeRateHistory est disponible a
    // la date d'emission, en pivotant par le XAF (Doc/proforma_generation_addendum.md,
    // section 0/1). Silencieusement omis si aucun taux n'est disponible, y compris pour
    // la devise de la commande elle-meme (dans ce cas: aucune equivalence n'est calculable,
    // tableau vide retourne plutot que de bloquer l'emission).
    private function computeCurrencyEquivalents(SalesOrder $salesOrder): array
    {
        $orderCurrency = $salesOrder->currency()->firstOrFail();
        $today = now()->toDateString();

        if ($orderCurrency->code === 'XAF') {
            $orderRateToXaf = 1.0;
        } else {
            // whereDate() (et non where() sur une simple chaine) : le cast 'date' d'Eloquent
            // serialise effective_date avec un composant horaire ("Y-m-d 00:00:00") a
            // l'ecriture, une comparaison chaine sur une simple date ("Y-m-d") exclurait a
            // tort les taux dates d'aujourd'hui (bug trouve lors de la verification en sandbox).
            $orderRate = ExchangeRateHistory::query()
                ->where('currency_id', $orderCurrency->id)
                ->whereDate('effective_date', '<=', $today)
                ->orderByDesc('effective_date')
                ->first();

            if (! $orderRate) {
                return [];
            }

            $orderRateToXaf = (float) $orderRate->rate_to_xaf;
        }

        $xafAmount = (float) $salesOrder->total_amount * $orderRateToXaf;

        $equivalents = [];

        foreach (Currency::query()->where('is_active', true)->where('id', '!=', $orderCurrency->id)->get() as $currency) {
            $rate = ExchangeRateHistory::query()
                ->where('currency_id', $currency->id)
                ->whereDate('effective_date', '<=', $today)
                ->orderByDesc('effective_date')
                ->first();

            if (! $rate || (float) $rate->rate_to_xaf <= 0) {
                continue;
            }

            $equivalents[] = [
                'currency_id' => $currency->id,
                'code' => $currency->code,
                'rate_to_xaf' => (string) $rate->rate_to_xaf,
                'amount' => number_format($xafAmount / (float) $rate->rate_to_xaf, 2, '.', ''),
            ];
        }

        return $equivalents;
    }

    private function renderAndAttachPdf(Invoice $invoice, CompanySettings $companySettings, Request $request): void
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

        // Gabarit comparatif (Doc/proforma_comparatif_addendum.md, decision n°2) reserve
        // au type PRODUIT_UNIQUE_MULTI_CHOIX, et seulement s'il reste au moins 2 options
        // "comparables" (niveau parmi les 3 colonnes du wireframe) apres emission : une
        // commande avec un seul choix comparable (ou uniquement des lignes STANDARD, qui
        // ne correspond a aucune des 3 colonnes) retombe sur le gabarit plat existant,
        // deviation documentee car non explicitement tranchee par l'addendum.
        $options = [];
        if ($invoice->salesOrder?->type === SalesOrderType::PRODUIT_UNIQUE_MULTI_CHOIX) {
            $invoice->load([
                'items.salesOrderItem.productVariant.product.attachments.mediaTypes',
                'items.salesOrderItem.productVariant.attributeValues.attributeValue',
            ]);
            $options = $this->buildComparatifOptions($invoice);
        }

        // Rendu Blade dans la langue du document (dompdf compile la vue dans loadView()).
        $pdf = $this->withDocumentLocale($invoice->language, function () use ($options, $invoice, $companySettings, $paymentMethods, $logoBase64) {
            if (count($options) >= 2) {
                // Meme produit pour les 3 options (comparatif d'un seul et meme besoin,
                // Doc/proforma_comparatif_addendum.md, §1) : l'image/nom/description du bloc
                // "PRODUIT / BESOIN" sont lus une seule fois, depuis la premiere option.
                $product = $options[0]['product'] ?? null;

                return Pdf::loadView('pdf.invoice_proforma_comparatif', [
                    'invoice' => $invoice,
                    'company' => $companySettings,
                    'paymentMethods' => $paymentMethods,
                    'logoBase64' => $logoBase64,
                    'options' => $options,
                    'criteria' => ProductAttribute::query()->orderBy('id')->get(),
                    'shipping' => $this->buildShippingEstimates($invoice->salesOrder),
                    'product' => $product,
                    'productImageBase64' => $this->productImageBase64($product),
                ])->setPaper('a4');
            }

            return Pdf::loadView('pdf.invoice_proforma', [
                'invoice' => $invoice,
                'company' => $companySettings,
                'paymentMethods' => $paymentMethods,
                'logoBase64' => $logoBase64,
            ])->setPaper('a4');
        });

        $fileName = 'proforma-'.str_replace(['/', ' '], '-', $invoice->invoice_number).'.pdf';
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

    // Ordre des colonnes du wireframe (Doc/NJ_Global_Trade_Template_Wireframe.pdf, page 1) :
    // Premier choix / Deuxieme choix / Troisieme choix. STANDARD (et l'absence de variante,
    // ex. lignes SERVICE) ne correspond a aucune des 3 colonnes et est relegue en fin de
    // tri (§D de la consigne : "decide d'un fallback sensible").
    private function levelSortIndex(?VariantLevel $level): int
    {
        return match ($level) {
            VariantLevel::PREMIER_CHOIX => 0,
            VariantLevel::DEUXIEME_CHOIX => 1,
            VariantLevel::TROISIEME_CHOIX => 2,
            default => 3,
        };
    }

    private function levelLabel(?VariantLevel $level): array
    {
        return match ($level) {
            VariantLevel::PREMIER_CHOIX => ['title' => 'Premier choix', 'subtitle' => 'Premium'],
            VariantLevel::DEUXIEME_CHOIX => ['title' => 'Deuxième choix', 'subtitle' => 'Meilleur compromis'],
            VariantLevel::TROISIEME_CHOIX => ['title' => 'Troisième choix', 'subtitle' => 'Volume négocié'],
            default => ['title' => $level?->value ?? 'Option', 'subtitle' => null],
        };
    }

    // Construit les options du comparatif (Doc/proforma_comparatif_addendum.md, §1) a
    // partir des invoice_items deja crees (immuables) et de donnees catalogue lues au
    // moment de l'emission (MOQ, criteres techniques) — jamais persistees sur l'invoice
    // elle-meme, hormis via le PDF genere. Seules les lignes dont le niveau correspond a
    // une des 3 colonnes du wireframe sont retenues ; une ligne STANDARD (ou sans variante)
    // est silencieusement exclue du comparatif (voir commentaire de renderAndAttachPdf()).
    private function buildComparatifOptions(Invoice $invoice): array
    {
        $comparableLevels = [VariantLevel::PREMIER_CHOIX, VariantLevel::DEUXIEME_CHOIX, VariantLevel::TROISIEME_CHOIX];

        $items = $invoice->items
            ->filter(fn (InvoiceItem $item) => in_array($item->salesOrderItem?->productVariant?->level, $comparableLevels, true))
            ->sortBy(fn (InvoiceItem $item) => $this->levelSortIndex($item->salesOrderItem?->productVariant?->level))
            ->values();

        $client = $invoice->client;
        $proposalDetails = $invoice->proposal_details ?? [];
        // TVA du comparatif (Doc/tva_addendum.md) : le taux figé sur la proforma est
        // appliqué option par option à la base « sous-total ligne + commission ligne »,
        // pour que chaque colonne du wireframe affiche son propre TTC.
        $taxRate = $invoice->tax_rate !== null ? (float) $invoice->tax_rate : 0.0;

        return $items->map(function (InvoiceItem $item) use ($client, $proposalDetails, $taxRate) {
            $variant = $item->salesOrderItem?->productVariant;
            $level = $variant?->level;

            $attributeValues = [];
            foreach ($variant?->attributeValues ?? [] as $value) {
                $attributeValues[$value->product_attribute_id] = $value->custom_value ?? $value->attributeValue?->value;
            }

            $commissionAmount = $this->resolveLineCommission($client, (float) $item->subtotal);
            $preTax = round((float) $item->subtotal + $commissionAmount, 2);
            $taxAmount = $taxRate > 0 ? round($preTax * $taxRate / 100, 2) : 0.0;
            $details = $proposalDetails[$level?->value] ?? [];

            return [
                'level' => $level,
                'label' => $this->levelLabel($level),
                'item' => $item,
                'variant' => $variant,
                'product' => $variant?->product,
                'moq' => $variant?->moq,
                'estimated_weight_kg' => $item->salesOrderItem?->estimated_weight_kg,
                'attribute_values' => $attributeValues,
                'commission_amount' => $commissionAmount,
                'tax_rate' => $taxRate > 0 ? $taxRate : null,
                'tax_amount' => $taxAmount,
                'total_amount' => round($preTax + $taxAmount, 2),
                'points_forts' => $details['points_forts'] ?? [],
                'points_attention' => $details['points_attention'] ?? [],
                'recommandation' => $details['recommandation'] ?? null,
            ];
        })->all();
    }

    // Commission recalculee a la volee par option (Doc/proforma_comparatif_addendum.md,
    // decision n°3) : priorite au taux personnalise du client, sinon palier actif de
    // commission_rules couvrant le sous-total de CETTE ligne — logique identique a
    // SalesOrderController::resolveCommission(), reappliquee independamment a chaque
    // sous-total plutot qu'un prorata de sales_orders.commission_amount. Jamais persiste.
    private function resolveLineCommission(?Client $client, float $subtotal): float
    {
        if ($client?->has_custom_commission) {
            return round($subtotal * (float) $client->custom_commission_rate / 100, 2);
        }

        $rule = CommissionRule::resolveFor($subtotal);

        if (! $rule) {
            return 0.0;
        }

        return $rule->commission_type === CommissionType::POURCENTAGE
            ? round($subtotal * (float) $rule->rate_or_amount / 100, 2)
            : round((float) $rule->rate_or_amount, 2);
    }

    // Bloc logistique aerien/maritime (decision n°6) : UNE estimation par mode pour tout
    // le document (pas par option), a partir de sales_orders.estimated_weight_kg/
    // estimated_volume_cbm (deja existants au niveau commande) et de la grille active
    // shipping_rates. Omis silencieusement si la commande n'a pas de poids/volume estime,
    // ou si aucun palier actif ne couvre la quantite.
    private function buildShippingEstimates(?SalesOrder $salesOrder): array
    {
        if (! $salesOrder) {
            return [];
        }

        $estimates = [];

        if ($salesOrder->estimated_weight_kg !== null) {
            $weight = (float) $salesOrder->estimated_weight_kg;
            $rate = ShippingRate::resolveFor(ShippingMode::AERIEN->value, $weight);
            if ($rate) {
                $estimates['AERIEN'] = [
                    'quantity' => $weight,
                    'unit' => $rate->unit,
                    'rate' => (float) $rate->rate,
                    'lead_time_label' => $rate->lead_time_label,
                    'estimated_cost' => round($weight * (float) $rate->rate, 2),
                ];
            }
        }

        if ($salesOrder->estimated_volume_cbm !== null) {
            $volume = (float) $salesOrder->estimated_volume_cbm;
            $rate = ShippingRate::resolveFor(ShippingMode::MARITIME->value, $volume);
            if ($rate) {
                $estimates['MARITIME'] = [
                    'quantity' => $volume,
                    'unit' => $rate->unit,
                    'rate' => (float) $rate->rate,
                    'lead_time_label' => $rate->lead_time_label,
                    'estimated_cost' => round($volume * (float) $rate->rate, 2),
                ];
            }
        }

        return $estimates;
    }

    // Image produit du bloc "PRODUIT / BESOIN" (decision D) : meme pattern que le logo
    // societe (Attachment polymorphe, is_primary en priorite). Retourne null si aucune
    // piece jointe de type PRODUCT_IMAGE n'existe — le gabarit affiche alors l'espace
    // reserve du wireframe ("EMPLACEMENT IMAGE PRODUIT").
    private function productImageBase64(?Product $product): ?string
    {
        if (! $product) {
            return null;
        }

        $attachment = $product->attachments
            ->filter(fn ($attachment) => $attachment->mediaTypes->contains(fn ($mediaType) => $mediaType->type === AttachmentType::PRODUCT_IMAGE))
            ->sortByDesc('is_primary')
            ->first();

        if (! $attachment || ! Storage::disk('public')->exists($attachment->file_path)) {
            return null;
        }

        $mime = $attachment->mime_type ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($attachment->file_path));
    }
}
