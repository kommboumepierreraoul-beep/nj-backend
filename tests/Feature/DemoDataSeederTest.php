<?php

namespace Tests\Feature;

use App\Enums\SalesOrderType;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\CompanySettings;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jeu de données de démonstration (`php artisan db:seed --class=DemoDataSeeder`) :
 * base peuplée pour les tests manuels de l'équipe. Doit être idempotent et
 * n'écrire aucune donnée fictive en production.
 */
class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_a_usable_demo_dataset(): void
    {
        (new DemoDataSeeder)->run();

        // Équipe de démonstration : ADMIN, connectable directement (pas de changement
        // de mot de passe forcé).
        $commercial = User::query()->where('email', 'commercial.demo@njglobaltrade.com')->first();
        $this->assertNotNull($commercial);
        $this->assertSame(UserRole::ADMIN, $commercial->role);
        $this->assertFalse($commercial->must_change_password);
        $this->assertTrue($commercial->is_active);

        // Catalogue + fournisseurs + clients.
        $this->assertGreaterThanOrEqual(4, Supplier::query()->count());
        $this->assertGreaterThanOrEqual(5, Client::query()->count());
        $this->assertGreaterThanOrEqual(5, Product::query()->count());
        $this->assertGreaterThanOrEqual(10, ProductVariant::query()->count());
        $this->assertGreaterThanOrEqual(8, ProductSupplier::query()->count());

        // Arguments proforma stockés sur les variantes comparatives.
        $premium = ProductVariant::query()->where('sku', 'NJ-ELE-0138-P')->firstOrFail();
        $this->assertNotEmpty($premium->proforma_strengths);
        $this->assertNotEmpty($premium->proforma_recommendation);

        // Valeurs par défaut de la proforma comparative au niveau société.
        $company = CompanySettings::current();
        $this->assertNotEmpty($company->default_proforma_conditions);
        $this->assertNotEmpty($company->default_proforma_payment_terms);

        // Commande comparative prête à émettre une proforma (3 options proposées).
        $comparatif = SalesOrder::query()->where('reference', 'DEMO-CMP-0001')->with('items')->firstOrFail();
        $this->assertSame(SalesOrderType::PRODUIT_UNIQUE_MULTI_CHOIX, $comparatif->type);
        $this->assertCount(3, $comparatif->items);
        $this->assertTrue($comparatif->items->every(fn ($item) => $item->is_proposed_option));

        // Une commande de prestation de service.
        $service = SalesOrder::query()->where('reference', 'DEMO-SRV-0004')->with('items')->firstOrFail();
        $this->assertSame('SERVICE', $service->items->first()->item_type->value);
    }

    public function test_it_is_idempotent(): void
    {
        (new DemoDataSeeder)->run();
        $counts = [
            'users' => User::query()->count(),
            'suppliers' => Supplier::query()->count(),
            'clients' => Client::query()->count(),
            'products' => Product::query()->count(),
            'variants' => ProductVariant::query()->count(),
            'orders' => SalesOrder::query()->count(),
            'items' => SalesOrderItem::query()->count(),
        ];

        (new DemoDataSeeder)->run();

        $this->assertSame($counts, [
            'users' => User::query()->count(),
            'suppliers' => Supplier::query()->count(),
            'clients' => Client::query()->count(),
            'products' => Product::query()->count(),
            'variants' => ProductVariant::query()->count(),
            'orders' => SalesOrder::query()->count(),
            'items' => SalesOrderItem::query()->count(),
        ]);
    }

    public function test_it_writes_nothing_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        (new DemoDataSeeder)->run();

        $this->assertDatabaseMissing('users', ['email' => 'commercial.demo@njglobaltrade.com']);
        $this->assertDatabaseMissing('sales_orders', ['reference' => 'DEMO-CMP-0001']);
    }
}
