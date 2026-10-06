<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'reference' => $this->reference,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'is_sensitive' => $this->is_sensitive,
            'sensitivity_reason' => $this->sensitivity_reason,
            'default_unit_id' => $this->default_unit_id,
            'default_weight_kg' => $this->default_weight_kg,
            'default_volume_cbm' => $this->default_volume_cbm,
            'min_order_quantity' => $this->min_order_quantity,
            // Compteur pour la colonne « Variantes » de la liste / du catalogue
            // (ProductController::index -> withCount('variants')).
            'variants_count' => $this->whenCounted('variants'),
            'brand' => $this->brand,
            'country_of_origin_id' => $this->country_of_origin_id,
            'created_by_user_id' => $this->created_by_user_id,
            'category' => new ProductCategoryResource($this->whenLoaded('category')),
            'default_unit' => $this->whenLoaded('defaultUnit'),
            'country_of_origin' => $this->whenLoaded('countryOfOrigin'),
            'translations' => $this->whenLoaded('translations'),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'tags' => $this->whenLoaded('tags'),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            // Bug corrige (2026-08-31, "les images ne s'affichent pas dans la
            // fiche produit") : ce champ n'existait tout simplement pas dans
            // la resource alors que le frontend (`Product.primary_image_url`,
            // consomme par la grille `/catalogue` et la colonne "Produit" des
            // listes) l'attendait depuis le debut — toujours `undefined`,
            // d'ou l'absence totale de vignette. Calcule ici a partir des
            // pieces jointes deja chargees (ProductController::index()/show()
            // chargent toutes deux `attachments.mediaTypes`), jamais par une
            // requete N+1 supplementaire.
            'primary_image_url' => $this->primaryImageUrl(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function primaryImageUrl(): ?string
    {
        if (! $this->relationLoaded('attachments')) {
            return null;
        }

        $images = $this->attachments
            ->filter(function ($attachment) {
                if (! $attachment->relationLoaded('mediaTypes')) {
                    return false;
                }

                return $attachment->mediaTypes->contains(
                    fn ($mediaType) => ($mediaType->type?->value ?? $mediaType->type) === 'PRODUCT_IMAGE',
                );
            })
            ->sort(fn ($a, $b) => ($b->is_primary <=> $a->is_primary) ?: ($a->sort_order <=> $b->sort_order));

        $primary = $images->first();

        return $primary && $primary->file_path ? Storage::disk('public')->url($primary->file_path) : null;
    }
}
