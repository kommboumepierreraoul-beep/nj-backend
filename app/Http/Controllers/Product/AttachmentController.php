<?php

namespace App\Http\Controllers\Product;

use App\Enums\AttachmentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController extends Controller
{
    // Types d'entites pouvant recevoir des pieces jointes polymorphes.
    // 'sales_order' ajoute pour le module "commandes clients" (Doc/commandes_modele_donnees.md,
    // section 2.1) : proforma PDF, recu PDF, bon de livraison.
    // 'invoice' ajoute pour le module "factures/proforma" (Doc/proforma_generation_addendum.md,
    // section 3) : PDF proforma/facture/avoir generes via ProformaController, egalement
    // consultables/gerables via ces endpoints generiques.
    private const ATTACHABLE_TYPES = [
        'product' => Product::class,
        'supplier' => Supplier::class,
        'client' => Client::class,
        'sales_order' => SalesOrder::class,
        'invoice' => Invoice::class,
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attachable_type' => ['required', Rule::in(array_keys(self::ATTACHABLE_TYPES))],
            'attachable_id' => ['required', 'integer'],
        ]);

        $attachments = Attachment::query()
            ->where('attachable_type', self::ATTACHABLE_TYPES[$validated['attachable_type']])
            ->where('attachable_id', $validated['attachable_id'])
            ->with('mediaTypes')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => AttachmentResource::collection($attachments)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attachable_type' => ['required', Rule::in(array_keys(self::ATTACHABLE_TYPES))],
            'attachable_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'max:20480'],
            'media_types' => ['required', 'array', 'min:1'],
            'media_types.*' => [new Enum(AttachmentType::class)],
            'is_primary' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $modelClass = self::ATTACHABLE_TYPES[$validated['attachable_type']];
        if (! $modelClass::query()->whereKey($validated['attachable_id'])->exists()) {
            return response()->json(['message' => 'Element cible introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $file = $request->file('file');
        $path = $file->store('attachments/'.$validated['attachable_type'], 'public');

        $attachment = Attachment::query()->create([
            'attachable_type' => $modelClass,
            'attachable_id' => $validated['attachable_id'],
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_kb' => (int) round($file->getSize() / 1024),
            'is_primary' => $validated['is_primary'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
            'uploaded_by_user_id' => $request->user()->id,
            'uploaded_at' => now(),
        ]);

        foreach (array_unique($validated['media_types']) as $mediaType) {
            $attachment->mediaTypes()->create(['type' => $mediaType]);
        }

        AuditLog::record(
            'attachment.created',
            $attachment,
            $request->user(),
            null,
            $attachment->only(['id', 'attachable_type', 'attachable_id', 'file_name', 'file_path', 'is_primary', 'sort_order']),
        );

        return response()->json([
            'message' => 'Piece jointe ajoutee.',
            'data' => new AttachmentResource($attachment->load('mediaTypes')),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, Attachment $attachment): JsonResponse
    {
        $validated = $request->validate([
            'media_types' => ['sometimes', 'array', 'min:1'],
            'media_types.*' => [new Enum(AttachmentType::class)],
            'is_primary' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $old = $attachment->only(['is_primary', 'sort_order']);

        $attachment->update(collect($validated)->except('media_types')->all());

        if (isset($validated['media_types'])) {
            $attachment->mediaTypes()->delete();
            foreach (array_unique($validated['media_types']) as $mediaType) {
                $attachment->mediaTypes()->create(['type' => $mediaType]);
            }
        }

        AuditLog::record(
            'attachment.updated',
            $attachment,
            $request->user(),
            $old,
            $attachment->only(['is_primary', 'sort_order']),
        );

        return response()->json([
            'message' => 'Piece jointe mise a jour.',
            'data' => new AttachmentResource($attachment->load('mediaTypes')),
        ]);
    }

    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        // Ecart corrige (audit facture, Doc/factures_modele_donnees.md, section 9) : la
        // suppression d'une piece jointe d'un document Invoice (PDF proforma/facture/avoir)
        // n'etait tracee dans aucun journal, alors que son emission/remplacement le sont
        // deja (ProformaController::store()). On journalise desormais ce cas specifiquement,
        // contre l'entite Invoice elle-meme (et non Attachment, deja supprimee au moment de
        // l'ecriture) pour rester consultable depuis l'historique du document. Le
        // comportement pour les autres types (product/supplier/client/sales_order) reste
        // volontairement inchange : la portee de cette correction se limite au gap identifie
        // sur le module factures, un durcissement plus large du controle d'acces (aujourd'hui
        // "role:SUPER_ADMIN,ADMIN", partage entre tous les types) sortirait de ce perimetre et
        // risquerait de casser le comportement deja teste des autres modules.
        $isInvoiceDocument = $attachment->attachable_type === Invoice::class;
        $invoiceId = $attachment->attachable_id;
        $snapshot = $attachment->only(['id', 'attachable_type', 'attachable_id', 'file_name', 'file_path']);

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->mediaTypes()->delete();
        $attachment->delete();

        if ($isInvoiceDocument && $invoice = Invoice::find($invoiceId)) {
            AuditLog::record('invoice.attachment_deleted', $invoice, $request->user(), $snapshot, null);
        } else {
            // Piece jointe generale journalisee (product/supplier/client/sales_order),
            // memes conditions que ci-dessus : entite Attachment deja supprimee, on
            // continue de passer l'objet PHP (attributs encore en memoire) pour rester
            // exploitable depuis l'historique.
            AuditLog::record('attachment.deleted', $attachment, $request->user(), $snapshot, null);
        }

        return response()->json(['message' => 'Piece jointe supprimee.']);
    }
}
