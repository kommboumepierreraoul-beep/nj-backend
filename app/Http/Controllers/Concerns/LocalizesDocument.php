<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\DocumentLanguage;
use Illuminate\Support\Facades\App;

// Rendu bilingue des documents PDF (proforma, facture, avoir, recu, comparatif).
// La langue vient de `invoices.language` (enum DocumentLanguage), elle-meme
// derivee de `clients.preferred_language` ou d'un choix explicite a l'emission.
// Les libelles sont dans lang/{fr,en}/documents.php. Voir
// Doc/documents_bilingues_addendum.md.
trait LocalizesDocument
{
    /**
     * Resout la langue effective d'une emission : parametre de requete >
     * preference client > FR. `$clientPreference` accepte n'importe quel enum
     * backed a valeurs FR/EN (ClientLanguage, DocumentLanguage) ou une chaine.
     */
    protected function resolveDocumentLanguage(?string $requested, \BackedEnum|string|null $clientPreference): DocumentLanguage
    {
        if ($requested !== null && $requested !== '') {
            return DocumentLanguage::tryFrom(strtoupper($requested)) ?? DocumentLanguage::FR;
        }

        $preference = $clientPreference instanceof \BackedEnum ? $clientPreference->value : $clientPreference;

        return $preference !== null
            ? (DocumentLanguage::tryFrom(strtoupper((string) $preference)) ?? DocumentLanguage::FR)
            : DocumentLanguage::FR;
    }

    /** Code de locale Laravel ("fr"/"en") pour une langue de document (enum FR/EN ou chaine). */
    protected function documentLocale(\BackedEnum|string|null $language): string
    {
        $value = $language instanceof \BackedEnum ? $language->value : $language;

        return strtoupper((string) $value) === DocumentLanguage::EN->value ? 'en' : 'fr';
    }

    /**
     * Execute $callback avec la locale de l'application posee sur celle du
     * document, puis la restaure (le rendu Blade de dompdf se fait dans
     * `Pdf::loadView()`, donc la locale doit etre active a ce moment-la).
     *
     * @template T
     * @param  \Closure():T  $callback
     * @return T
     */
    protected function withDocumentLocale(\BackedEnum|string|null $language, \Closure $callback)
    {
        $previous = App::getLocale();
        App::setLocale($this->documentLocale($language));

        try {
            return $callback();
        } finally {
            App::setLocale($previous);
        }
    }
}
