<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanySettingsResource;
use App\Models\AuditLog;
use App\Models\CompanySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Ecran "Parametres -> Entreprise -> Identite de l'entreprise"
 * (Doc/design_system_maquette_complete.md, §5.10 / §8.2 ; Doc/proforma_generation_addendum.md,
 * section 2.1). Table singleton `company_settings` (id=1, seedee par sa migration de
 * creation) : lecture derriere "company_settings.view", modification derriere
 * "company_settings.manage" (permissions deja seedees par
 * 2026_08_17_000005_seed_invoice_and_company_permissions_table). Aucune creation ni
 * suppression exposee — la ligne unique existe toujours.
 *
 * Ces informations alimentent directement l'en-tete et le pied des documents PDF
 * (ProformaController, InvoiceController, CreditNoteController) et le numero WhatsApp
 * expediteur (WhatsAppDocumentSender::companyWhatsAppNumber()).
 */
class CompanySettingsController extends Controller
{
    private const AUDITED_FIELDS = [
        'legal_name', 'tagline', 'address_line', 'representation_line', 'phone',
        'whatsapp', 'email', 'website', 'logo_path', 'default_proforma_validity_days',
        'default_proforma_conditions', 'default_proforma_production_delay',
        'default_proforma_payment_terms', 'default_proforma_customs',
    ];

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => new CompanySettingsResource(CompanySettings::current()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'legal_name' => ['sometimes', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'address_line' => ['sometimes', 'string', 'max:255'],
            'representation_line' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'whatsapp' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            // Deux voies pour le logo : soit un fichier a televerser ('logo'), soit un
            // chemin deja present sur le disque public ('logo_path', ex. 'company/logo.png').
            'logo' => ['sometimes', 'file', 'image', 'max:5120'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_proforma_validity_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            // Taux de TVA propose par defaut a la creation d'une commande (Doc/tva_addendum.md).
            'default_tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            // Bloc "Notes / conditions" par defaut de la proforma comparative.
            'default_proforma_conditions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'default_proforma_production_delay' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'default_proforma_payment_terms' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'default_proforma_customs' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $settings = CompanySettings::current();
        $old = $settings->only(self::AUDITED_FIELDS);

        if ($request->hasFile('logo')) {
            // Stocke sous 'company/' sur le disque public, comme le seed de reference
            // (company/logo.png). L'ancien fichier n'est pas supprime automatiquement :
            // un document PDF deja genere pourrait encore y faire reference.
            $validated['logo_path'] = $request->file('logo')->store('company', 'public');
        }

        unset($validated['logo']);

        $settings->update($validated);

        AuditLog::record(
            'company_settings.updated',
            $settings,
            $request->user(),
            $old,
            $settings->fresh()->only(self::AUDITED_FIELDS),
        );

        return response()->json([
            'message' => 'Parametres societe mis a jour.',
            'data' => new CompanySettingsResource($settings->fresh()),
        ]);
    }

    /**
     * Supprime le fichier logo courant (repli sur le comportement "pas de logo").
     * Expose separement de update() pour rester explicite : c'est une action
     * destructrice sur un fichier, pas une simple edition de champ.
     */
    public function removeLogo(Request $request): JsonResponse
    {
        $settings = CompanySettings::current();

        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $old = $settings->only(['logo_path']);
        $settings->update(['logo_path' => null]);

        AuditLog::record('company_settings.updated', $settings, $request->user(), $old, ['logo_path' => null]);

        return response()->json([
            'message' => 'Logo supprime.',
            'data' => new CompanySettingsResource($settings->fresh()),
        ]);
    }
}
