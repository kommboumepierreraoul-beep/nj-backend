<?php

namespace App\Enums;

enum AttachmentType: string
{
    case PRODUCT_IMAGE = 'PRODUCT_IMAGE';
    case PAYMENT_PROOF = 'PAYMENT_PROOF';
    case SUPPLIER_DOCUMENT = 'SUPPLIER_DOCUMENT';
    case CLIENT_DOCUMENT = 'CLIENT_DOCUMENT';
    // Ajouts module "commandes clients" (Doc/commandes_modele_donnees.md, section 2.1) :
    // documents rattaches a une SalesOrder via la table polymorphe attachments.
    // PAYMENT_PROOF (deja existant ci-dessus) est reutilise tel quel pour les
    // preuves de paiement rattachees a une SalesOrderPayment.
    case SALES_ORDER_PROFORMA = 'SALES_ORDER_PROFORMA';
    case SALES_ORDER_RECEIPT = 'SALES_ORDER_RECEIPT';
    case SALES_ORDER_DELIVERY_NOTE = 'SALES_ORDER_DELIVERY_NOTE';
    // Ajout module "factures/proforma" (Doc/factures_modele_donnees.md, section 2.1) :
    // un seul cas suffit pour tout document Invoice (PROFORMA/FACTURE/AVOIR), puisque
    // invoices.document_type distingue deja le type de document. SALES_ORDER_PROFORMA/
    // SALES_ORDER_RECEIPT restent dans l'enum pour compatibilite mais l'usage se deplace
    // naturellement vers INVOICE_DOCUMENT.
    case INVOICE_DOCUMENT = 'INVOICE_DOCUMENT';
    case OTHER = 'OTHER';
}
