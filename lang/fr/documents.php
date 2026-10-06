<?php

// Libellés des documents PDF émis (proforma, facture, avoir, reçu, comparatif).
// La langue du rendu est pilotée par `invoices.language` (enum DocumentLanguage,
// FR/EN), elle-même dérivée de `clients.preferred_language` ou d'un choix
// explicite à l'émission. Voir Doc/documents_bilingues_addendum.md.

return [

    'meta' => [
        'generated_by' => 'Document généré par la plateforme NJ Global Trade',
        'page' => 'page',
        'comparatif_suffix' => 'comparatif',
    ],

    'common' => [
        'reference' => 'Référence',
        'issue_date' => "Date d'émission",
        'valid_until' => 'Valable jusqu’au',
        'issued_by' => 'Émis par',
        'billed_to' => 'Adressé à',
        'received_from' => 'Reçu de',
        'billing_mode' => 'Mode de facturation',
        'linked_order' => 'Commande liée',
    ],

    'billing_mode' => [
        'COMMISSION_VISIBLE' => 'Commission visible',
        'PRIX_GLOBAL' => 'Prix global',
    ],

    'items' => [
        'designation' => 'Désignation',
        'quantity' => 'Quantité',
        'unit_price' => 'Prix unitaire',
        'subtotal' => 'Sous-total',
        'goods_subtotal' => 'Sous-total marchandise',
        'credited_subtotal' => 'Sous-total crédité',
    ],

    'section' => [
        'order_detail' => 'Détail de la commande',
        'credit_detail' => "Détail de l'avoir",
        'settled_order_detail' => 'Détail de la commande réglée',
        'transport' => 'Transport',
        'financial_summary' => 'Récapitulatif financier',
        'summary' => 'Récapitulatif',
        'payment_methods' => 'Moyens de paiement acceptés',
        'payment_detail' => 'Détail du règlement',
    ],

    'transport' => [
        'mode' => 'Mode',
        'est_weight' => 'Poids estimé',
        'est_volume' => 'Volume estimé',
        'lead_time' => 'Délai indicatif',
        'note' => 'Poids et volumes indicatifs, avec une marge de ±10 à 15 % — valeurs définitives confirmées à l’expédition.',
        'AERIEN_STANDARD' => 'Fret aérien standard',
        'AERIEN_SENSIBLE' => 'Fret aérien sensible',
        'MARITIME' => 'Fret maritime',
        'NON_APPLICABLE' => 'Non applicable',
        'delay' => [
            'AERIEN_STANDARD' => '7 à 14 jours',
            'AERIEN_SENSIBLE' => '10 à 18 jours',
            'MARITIME' => '30 à 45 jours',
        ],
    ],

    'summary' => [
        'discount' => 'Remise',
        'commission' => 'Commission NJ Global Trade',
        'vat' => 'TVA',
        'total' => 'Total',
        'total_incl_tax' => 'Total TTC',
        'total_paid' => 'Total réglé',
        'total_paid_incl_tax' => 'Total réglé (TTC)',
        'total_credited' => 'Total crédité',
        'total_credited_incl_tax' => 'Total crédité (TTC)',
        'credited_subtotal_excl_tax' => 'Sous-total crédité (HT)',
    ],

    'currency' => [
        'multi_label' => 'Montant global exprimé en plusieurs devises',
        'reference' => 'devise de référence',
        'equiv_note_due' => 'Montant dû à la structure dans la devise de la commande (:code) — les autres devises sont des équivalences figées au taux du jour d’émission.',
        'equiv_note_paid' => 'Montant réglé dans la devise de la commande (:code) — les autres devises sont des équivalences figées au taux du jour d’émission.',
    ],

    'proforma' => [
        'title' => 'DEVIS PROFORMA',
        'title_comparatif' => 'DEVIS PROFORMA — COMPARATIF',
        'subtitle' => "Ce document est un devis — il n'a pas valeur de facture",
    ],

    'invoice' => [
        'title' => 'FACTURE',
        'subtitle' => 'Facture définitive — émise après encaissement intégral',
    ],

    'credit_note' => [
        'title' => 'AVOIR',
        'subtitle' => 'Note de crédit',
        'credited_line' => 'Cet avoir crédite le document :number (:type émise le :date).',
        'type_facture' => 'facture',
        'type_proforma' => 'proforma',
    ],

    'receipt' => [
        'title' => 'REÇU',
        'subtitle' => 'Reçu officiel de paiement',
        'amount_received' => 'Montant encaissé',
        'payment_method' => 'Mode de paiement',
        'payment_date' => "Date d'encaissement",
        'receipt_number' => 'N° de reçu',
        'transaction_ref' => 'Référence transaction',
    ],

    'stamp' => [
        'paid_in_full' => 'Payé intégralement',
        'paid' => 'Payé',
    ],

    'payment_method' => [
        'ORANGE_MONEY' => 'Orange Money',
        'VIREMENT_UBA' => 'Virement bancaire (UBA)',
        'WAVE' => 'Wave',
        'MTN_MOMO' => 'MTN MoMo',
        'ESPECES' => 'Espèces',
        'AUTRE' => 'Autre',
    ],

    'conclusion' => [
        'terms_validity' => 'Conditions et validité',
        'terms' => 'Conditions',
        'settlement_receipt' => 'Reçu de règlement',
        'payment_receipt' => 'Reçu de paiement',
        'thanks' => 'Merci de votre confiance — pour toute question, contactez-nous via WhatsApp ou par email.',
    ],

    'comparatif' => [
        'client_info' => 'Informations client',
        'client_name' => 'Nom du client',
        'country' => 'Pays',
        'phone' => 'Téléphone',
        'company' => 'Société',
        'address_city' => 'Adresse / Ville',
        'product_need' => 'Produit / besoin',
        'product_name' => 'Nom du produit',
        'description' => 'Description / caractéristiques',
        'package_type' => 'Type de colis',
        'package_image_placeholder' => 'EMPLACEMENT IMAGE PRODUIT',
        'package_standard' => 'Standard',
        'package_sensitive' => 'Sensible / Batterie',
        'options_comparison' => 'Comparatif des options',
        'criteria' => 'Critères',
        'level' => 'Niveau',
        'moq' => 'Quantité minimum (MOQ)',
        'quantity_ordered' => 'Quantité commandée',
        'recommendation' => 'Recommandation',
        'professional_advice' => 'Conseils professionnels',
        'option' => 'Option',
        'parameters' => 'Paramètres',
        'estimate' => 'Estimation',
        'air' => 'Aérien',
        'sea' => 'Maritime',
        'lead' => 'Délai',
        'rate' => 'Tarif',
        'estimated_cost' => 'Coût estimé',
        'unavailable_air' => 'Non disponible — poids estimé de la commande absent ou aucun tarif actif applicable.',
        'unavailable_sea' => 'Non disponible — volume estimé de la commande absent ou aucun tarif actif applicable.',
        'details_advantages' => 'Détails & avantages',
        'strengths_for' => 'Points forts — :option',
        'attention_points' => "Points d'attention",
        'not_provided' => 'Non renseigné.',
        'notes_conditions' => 'Notes / conditions',
        'commercial_terms' => 'Conditions commerciales',
        'production_lead' => 'Délai de production',
        'payment' => 'Paiement',
        'customs_delivery' => 'Douane / livraison',
    ],

    'legal' => [
        'proforma' => "Ce document est un devis (proforma) — il n'a pas valeur de facture ni de reçu. Les poids et volumes indiqués sont indicatifs, avec une marge de ±10 à 15 % ; les valeurs définitives seront confirmées à l'expédition. :validity_sentenceLa commande est considérée comme confirmée à réception d'un acompte ou du règlement total selon les modalités convenues avec notre équipe. Un reçu officiel vous sera délivré à réception de votre paiement.",
        'proforma_validity' => "Ce document reste valable jusqu'au :due. ",
        'invoice' => "Facture émise automatiquement après encaissement intégral du montant dû (cahier des charges NJ Global Trade, section 2.2). Ce document fait foi de règlement complet de la commande auprès de notre structure. Les poids et volumes indiqués restent indicatifs jusqu'à expédition, avec une marge de ±10 à 15 %. Merci de conserver ce document, il pourra vous être demandé pour tout suivi de votre commande.",
        'receipt' => "Reçu officiel de paiement (cahier des charges NJ Global Trade, section 2.2). Ce document atteste la réception du règlement porté ci-dessus et fait foi de paiement auprès de notre structure. Une facture définitive vous est également délivrée. Merci de conserver ce reçu, il pourra vous être demandé pour tout suivi de votre commande.",
        'credit_note' => "Cet avoir annule tout ou partie du document :number. Il vient en déduction du montant restant dû sur la commande associée et ne donne pas lieu à un remboursement automatique. Un remboursement effectif, s'il a lieu, est enregistré séparément (encaissement de type REMBOURSEMENT).",
    ],

];
