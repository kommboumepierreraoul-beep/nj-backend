<?php

namespace App\Enums;

// Mode physique d'acheminement tarife par shipping_rates (Doc/proforma_comparatif_
// addendum.md, decision n°6). Deliberement distinct de TransportMode (AERIEN_STANDARD/
// AERIEN_SENSIBLE/MARITIME/NON_APPLICABLE), qui qualifie le colis d'une commande : les
// deux enums ne doivent pas etre confondus.
enum ShippingMode: string
{
    case AERIEN = 'AERIEN';
    case MARITIME = 'MARITIME';
}
