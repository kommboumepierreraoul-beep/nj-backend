<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attachable_type' => $this->attachable_type,
            'attachable_id' => $this->attachable_id,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            // Bug corrige (2026-08-31, "les images ne s'affichent pas dans la
            // fiche produit") : cette resource ne renvoyait jamais d'URL
            // exploitable par le frontend (seulement `file_path`, le chemin
            // relatif sur le disque "public") — `Attachment.url` (type
            // `modules/attachments/types.ts`) etait donc toujours `undefined`
            // cote frontend, aussi bien pour les liens de telechargement que
            // pour l'affichage des vignettes image. Calcule via le disque
            // "public" (config/filesystems.php), coherent avec le stockage
            // fait par AttachmentController::store() (`$file->store(..., 'public')`).
            'url' => $this->file_path ? Storage::disk('public')->url($this->file_path) : null,
            'mime_type' => $this->mime_type,
            'size_kb' => $this->size_kb,
            // `size_bytes` ajoute pour matcher le contrat de type frontend
            // (`Attachment.size_bytes`) ; la colonne reelle en base reste
            // `size_kb` (voir migration create_attachments_table), non modifiee.
            'size_bytes' => $this->size_kb !== null ? $this->size_kb * 1024 : null,
            'is_primary' => $this->is_primary,
            'sort_order' => $this->sort_order,
            'uploaded_by_user_id' => $this->uploaded_by_user_id,
            'uploaded_at' => $this->uploaded_at,
            // La table `attachments` n'a pas de colonnes created_at/updated_at
            // (modele en `public $timestamps = false`, seule `uploaded_at`
            // existe) alors que le frontend (`Attachment.created_at`/
            // `updated_at`, affiche dans `attachment-dropzone.tsx` via
            // `formatDateTime(attachment.created_at)`) s'attend a les trouver.
            // On les fait pointer vers `uploaded_at` plutot que d'ajouter des
            // colonnes inutiles (une piece jointe n'est jamais modifiee apres
            // coup hormis `is_primary`/`sort_order`, deja trackes ailleurs).
            'created_at' => $this->uploaded_at,
            'updated_at' => $this->uploaded_at,
            'media_types' => $this->whenLoaded('mediaTypes', fn () => $this->mediaTypes->map(fn ($mediaType) => $mediaType->type?->value ?? $mediaType->type)),
        ];
    }
}
