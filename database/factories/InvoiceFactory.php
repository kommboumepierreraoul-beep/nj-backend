<?php

namespace Database\Factories;

use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_number' => 'NJG-'.fake()->unique()->numerify('####-####-###'),
            'sales_order_id' => SalesOrder::factory(),
            'document_type' => InvoiceDocumentType::PROFORMA->value,
            'version' => 1,
            'status' => InvoiceStatus::EMISE->value,
            'client_id' => Client::factory(),
            'client_name' => fake()->name(),
            'currency_id' => Currency::factory(),
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 0,
            'issued_at' => now(),
        ];
    }
}
