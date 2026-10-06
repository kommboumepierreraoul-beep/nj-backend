<?php

// Ce fichier reste volontairement minimal : chaque module metier definit ses
// propres routes (et ses propres groupes de middleware/permissions) dans son
// sous-repertoire.
require __DIR__.'/auth/auth.php';
require __DIR__.'/reference_data/reference_data.php';
require __DIR__.'/product/product.php';
require __DIR__.'/supplier/supplier.php';
require __DIR__.'/client/client.php';
require __DIR__.'/users/users.php';
require __DIR__.'/audit/audit.php';
require __DIR__.'/sales_order/sales_order.php';
require __DIR__.'/invoice/invoice.php';
require __DIR__.'/company/company.php';
require __DIR__.'/dashboard/dashboard.php';
require __DIR__.'/flow_analytics/flow_analytics.php';
require __DIR__.'/notifications/notifications.php';
