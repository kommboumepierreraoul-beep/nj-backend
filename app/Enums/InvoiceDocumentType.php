<?php

namespace App\Enums;

enum InvoiceDocumentType: string
{
    case PROFORMA = 'PROFORMA';
    case FACTURE = 'FACTURE';
    case AVOIR = 'AVOIR';
    // Reçu de paiement tamponné « PAYÉ » (cahier des charges NJ Global Trade v2, §2.2 :
    // « générer le numéro de reçu, produire le PDF avec cachet PAYÉ »). Émis
    // automatiquement par SalesOrderPaymentController au passage à PAYEE, en plus de la
    // FACTURE définitive (Doc/factures_recu_addendum.md). Numéro = REC-<référence commande>,
    // identique au receipt_number du mouvement d'encaissement qui le déclenche.
    case RECU = 'RECU';
}
