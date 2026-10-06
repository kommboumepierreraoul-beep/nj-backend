<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\ClientCategory;
use App\Models\CompanySettings;
use App\Models\ContactChannelType;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSupplier;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Jeu de données de démonstration pour que l'équipe puisse tester l'application
 * sur une base peuplée (produits + variantes avec arguments proforma, clients,
 * fournisseurs, liens produit-fournisseur, commandes clients dont une commande
 * comparative PRODUIT_UNIQUE_MULTI_CHOIX prête à émettre une proforma).
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * - **Jamais exécuté en production** : le garde-fou en tête de `run()` sort
 *   immédiatement si `app()->isProduction()` — aucune donnée fictive en prod.
 * - **Idempotent** : `updateOrCreate` sur des clés naturelles (email, SKU,
 *   raison sociale, référence de commande) — rejouable sans doublon.
 * - Les référentiels de base (pays, devises, unités, catégories produits,
 *   catégories clients, canaux de contact, barème de commission, tarifs de
 *   transport, paramètres société, moyens de paiement) sont déjà seedés par
 *   `DatabaseSeeder` et les migrations : ce seeder les appelle en préalable
 *   pour rester lançable seul, puis ne pose que la donnée métier.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    private const DEMO_PASSWORD = 'Demo!12345';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoDataSeeder ignoré : jamais de données de démonstration en production.');

            return;
        }

        $this->call([
            CountrySeeder::class,
            CurrencySeeder::class,
            UnitOfMeasureSeeder::class,
            ProductCategorySeeder::class,
        ]);

        $admin = $this->seedUsers();
        $this->seedCompanyProformaDefaults();
        $suppliers = $this->seedSuppliers($admin);
        $clients = $this->seedClients($admin);
        $variants = $this->seedCatalogue($admin, $suppliers);
        $this->seedSalesOrders($admin, $clients, $variants);

        $this->command?->info('DemoDataSeeder : équipe de démonstration, catalogue, clients, fournisseurs et commandes créés (mot de passe : '.self::DEMO_PASSWORD.').');
    }

    private function xaf(): Currency
    {
        return Currency::query()->where('code', 'XAF')->firstOrFail();
    }

    private function cny(): Currency
    {
        return Currency::query()->where('code', 'CNY')->firstOrFail();
    }

    private function country(string $iso): ?Country
    {
        return Country::query()->where('iso_code', $iso)->first();
    }

    private function seedUsers(): User
    {
        $team = [
            ['email' => 'commercial.demo@njglobaltrade.com', 'full_name' => 'Awa Commerciale'],
            ['email' => 'compta.demo@njglobaltrade.com', 'full_name' => 'Serge Comptable'],
            ['email' => 'logistique.demo@njglobaltrade.com', 'full_name' => 'Rita Logistique'],
        ];

        $first = null;
        foreach ($team as $member) {
            $user = User::query()->updateOrCreate(
                ['email' => $member['email']],
                [
                    'name' => $member['full_name'],
                    'full_name' => $member['full_name'],
                    'password' => self::DEMO_PASSWORD,
                    'must_change_password' => false,
                ],
            );
            // role / is_active ne sont pas mass-assignables (voir App\Models\User).
            $user->forceFill(['role' => UserRole::ADMIN->value, 'is_active' => true])->save();
            $first ??= $user;
        }

        return $first;
    }

    private function seedCompanyProformaDefaults(): void
    {
        // company_settings est un singleton seedé par sa migration : on ne fait que
        // renseigner les 4 valeurs par défaut du bloc "Notes / conditions" de la
        // proforma comparative (Doc/proforma_comparatif_addendum.md §4).
        CompanySettings::query()->whereKey(1)->update([
            'default_proforma_conditions' => 'Prix indiqués DDP Douala, hors taxes locales. Offre valable sous réserve de disponibilité au moment de la confirmation.',
            'default_proforma_production_delay' => '15 à 25 jours ouvrés après réception de l\'acompte, selon les volumes.',
            'default_proforma_payment_terms' => 'Acompte de 50 % à la commande, solde avant expédition. Virement bancaire, Orange Money ou espèces au bureau.',
            'default_proforma_customs' => 'Dédouanement et livraison finale à la charge du client, sauf mention contraire. Documents douaniers fournis par NJ Global Trade.',
        ]);
    }

    /** @return array<string, Supplier> indexé par code court */
    private function seedSuppliers(User $admin): array
    {
        $cn = $this->country('CN')?->id;

        $rows = [
            'hanfeng' => ['company_name' => 'Guangzhou Hanfeng Trading Co., Ltd', 'contact_name' => 'Li Wei', 'city' => 'Guangzhou', 'province' => 'Guangdong', 'reliability' => 'BON', 'email' => 'sales@hanfeng-gz.com', 'whatsapp' => '+86 138 0000 1111'],
            'shenzhen-tech' => ['company_name' => 'Shenzhen BrightTech Electronics', 'contact_name' => 'Chen Jie', 'city' => 'Shenzhen', 'province' => 'Guangdong', 'reliability' => 'EXCELLENT', 'email' => 'export@brighttech.cn', 'whatsapp' => '+86 139 2222 3333'],
            'yiwu-goods' => ['company_name' => 'Yiwu Sunrise Commodity', 'contact_name' => 'Zhang Min', 'city' => 'Yiwu', 'province' => 'Zhejiang', 'reliability' => 'MOYEN', 'email' => 'info@yiwu-sunrise.com', 'whatsapp' => '+86 137 4444 5555'],
            'ningbo-solar' => ['company_name' => 'Ningbo GreenPower Solar', 'contact_name' => 'Wang Fang', 'city' => 'Ningbo', 'province' => 'Zhejiang', 'reliability' => 'BON', 'email' => 'trade@greenpower-solar.cn', 'whatsapp' => '+86 136 6666 7777'],
        ];

        $suppliers = [];
        foreach ($rows as $key => $row) {
            $supplier = Supplier::query()->updateOrCreate(
                ['company_name' => $row['company_name']],
                [
                    'contact_name' => $row['contact_name'],
                    'phone' => $row['whatsapp'],
                    'whatsapp' => $row['whatsapp'],
                    'email' => $row['email'],
                    'city' => $row['city'],
                    'province' => $row['province'],
                    'country_id' => $cn,
                    'reliability' => $row['reliability'],
                    'is_verified' => in_array($row['reliability'], ['BON', 'EXCELLENT'], true),
                    'is_active' => true,
                    'is_blacklisted' => false,
                    'created_by_user_id' => $admin->id,
                ],
            );

            $supplier->contacts()->updateOrCreate(
                ['email' => $row['email']],
                ['full_name' => $row['contact_name'], 'role_title' => 'Responsable export', 'phone' => $row['whatsapp'], 'is_primary' => true],
            );

            $suppliers[$key] = $supplier;
        }

        return $suppliers;
    }

    /** @return array<int, Client> */
    private function seedClients(User $admin): array
    {
        $direct = ClientCategory::query()->where('code', 'DIRECT')->value('id');
        $ecom = ClientCategory::query()->where('code', 'ECOM_RICH')->value('id');
        $autre = ClientCategory::query()->where('code', 'AUTRE')->value('id');
        $email = ContactChannelType::query()->where('code', 'EMAIL')->value('id');
        $whatsapp = ContactChannelType::query()->where('code', 'WHATSAPP')->value('id');
        $xaf = $this->xaf()->id;

        $rows = [
            ['full_name' => 'Boutique Élégance Douala', 'legal_name' => 'Élégance SARL', 'type' => 'ENTREPRISE', 'category_id' => $direct, 'country' => 'CM', 'city' => 'Douala', 'segment' => 'ARGENT', 'contact' => 'contact@elegance-dla.cm', 'phone' => '+237 6 55 00 11 22'],
            ['full_name' => 'Kanabio Import', 'legal_name' => 'Kanabio Import Sarl', 'type' => 'ENTREPRISE', 'category_id' => $ecom, 'country' => 'CM', 'city' => 'Yaoundé', 'segment' => 'PLATINE', 'contact' => 'achats@kanabio.cm', 'phone' => '+237 6 99 33 44 55'],
            ['full_name' => 'Jean-Paul Mbarga', 'legal_name' => null, 'type' => 'PARTICULIER', 'category_id' => $direct, 'country' => 'CM', 'city' => 'Douala', 'segment' => 'BRONZE', 'contact' => 'jp.mbarga@gmail.com', 'phone' => '+237 6 77 88 99 00'],
            ['full_name' => 'Librairie du Savoir', 'legal_name' => 'Librairie du Savoir SA', 'type' => 'ENTREPRISE', 'category_id' => $autre, 'country' => 'GA', 'city' => 'Libreville', 'segment' => 'ARGENT', 'contact' => 'commande@librairie-savoir.ga', 'phone' => '+241 06 12 34 56'],
            ['full_name' => 'TechStore Abidjan', 'legal_name' => 'TechStore CI', 'type' => 'ENTREPRISE', 'category_id' => $ecom, 'country' => 'CI', 'city' => 'Abidjan', 'segment' => 'PLATINE', 'contact' => 'pro@techstore.ci', 'phone' => '+225 07 01 02 03'],
        ];

        $clients = [];
        foreach ($rows as $row) {
            $client = Client::query()->updateOrCreate(
                ['full_name' => $row['full_name']],
                [
                    'client_type' => $row['type'],
                    'legal_name' => $row['legal_name'],
                    'category_id' => $row['category_id'],
                    'country_id' => $this->country($row['country'])?->id,
                    'city' => $row['city'],
                    'preferred_currency_id' => $xaf,
                    'preferred_language' => 'FR',
                    'billing_mode' => 'COMMISSION_VISIBLE',
                    'has_custom_commission' => false,
                    'value_segment' => $row['segment'],
                    'status' => 'ACTIF',
                    'created_by_user_id' => $admin->id,
                ],
            );

            if ($email) {
                $client->contacts()->updateOrCreate(
                    ['channel_type_id' => $email, 'value' => $row['contact']],
                    ['label' => 'Email principal', 'is_preferred' => true],
                );
            }
            if ($whatsapp) {
                $client->contacts()->updateOrCreate(
                    ['channel_type_id' => $whatsapp, 'value' => $row['phone']],
                    ['label' => 'WhatsApp', 'is_preferred' => false],
                );
            }

            $clients[] = $client;
        }

        return $clients;
    }

    /**
     * Catalogue de démonstration. Les produits « comparatifs » ont 3 variantes
     * Premier / Deuxième / Troisième choix avec leurs arguments proforma stockés
     * (points forts / attention / recommandation) — c'est cette donnée qui est
     * reprise automatiquement à l'émission d'une proforma comparative.
     *
     * @param  array<string, Supplier>  $suppliers
     * @return array<string, ProductVariant> variantes indexées par SKU
     */
    private function seedCatalogue(User $admin, array $suppliers): array
    {
        $xaf = $this->xaf();
        $cny = $this->cny();
        $categoryId = fn (string $slug) => ProductCategory::query()->where('slug', $slug)->value('id');

        $catalogue = [
            [
                'reference' => 'NJ-ELE-0138',
                'name' => 'Écouteurs sans fil TWS Pro',
                'slug' => 'ecouteurs-sans-fil-tws-pro',
                'category' => 'ecouteurs',
                'brand' => 'Aurea',
                'description' => 'Écouteurs Bluetooth 5.3 avec réduction de bruit active, boîtier de charge USB-C.',
                'supplier' => 'shenzhen-tech',
                'variants' => [
                    [
                        'level' => 'PREMIER_CHOIX', 'sku' => 'NJ-ELE-0138-P', 'name' => 'TWS Pro — Premier choix',
                        'purchase_price' => 42.0, 'sale_price' => 12900, 'moq' => 100,
                        'proforma_strengths' => ['Réduction de bruit active la plus efficace', 'Autonomie 8 h + 32 h avec le boîtier', 'Certification IPX5, garantie 12 mois'],
                        'proforma_weaknesses' => ['Prix unitaire le plus élevé du comparatif'],
                        'proforma_recommendation' => 'Recommandé pour un positionnement premium et une clientèle exigeante sur la qualité audio.',
                    ],
                    [
                        'level' => 'DEUXIEME_CHOIX', 'sku' => 'NJ-ELE-0138-D', 'name' => 'TWS Pro — Deuxième choix',
                        'purchase_price' => 31.0, 'sale_price' => 9900, 'moq' => 150,
                        'proforma_strengths' => ['Meilleur rapport qualité / prix', 'Réduction de bruit passive efficace', 'Autonomie 6 h + 24 h'],
                        'proforma_weaknesses' => ['Pas de réduction de bruit active', 'Boîtier en plastique standard'],
                        'proforma_recommendation' => 'Le meilleur compromis pour un volume moyen : marge confortable, prix de vente accessible.',
                    ],
                    [
                        'level' => 'TROISIEME_CHOIX', 'sku' => 'NJ-ELE-0138-T', 'name' => 'TWS Pro — Troisième choix',
                        'purchase_price' => 19.0, 'sale_price' => 6500, 'moq' => 300,
                        'proforma_strengths' => ['Prix unitaire le plus bas', 'Idéal pour de gros volumes / revente'],
                        'proforma_weaknesses' => ['Autonomie limitée (4 h)', 'Pas de certification étanchéité', 'Garantie 6 mois'],
                        'proforma_recommendation' => 'À privilégier pour une commande à fort volume où le prix prime sur les finitions.',
                    ],
                ],
            ],
            [
                'reference' => 'NJ-ENE-0210',
                'name' => 'Chargeur USB-C 30W GaN',
                'slug' => 'chargeur-usb-c-30w-gan',
                'category' => 'chargeurs',
                'brand' => 'Voltek',
                'description' => 'Chargeur mural GaN compact, USB-C Power Delivery 30W, compatible smartphones et tablettes.',
                'supplier' => 'shenzhen-tech',
                'variants' => [
                    [
                        'level' => 'PREMIER_CHOIX', 'sku' => 'NJ-ENE-0210-P', 'name' => 'Chargeur 30W — Premier choix',
                        'purchase_price' => 6.4, 'sale_price' => 4900, 'moq' => 200,
                        'proforma_strengths' => ['Technologie GaN, très compact', 'Protection surtension / surchauffe', 'Câble USB-C tressé inclus'],
                        'proforma_weaknesses' => ['Coût unitaire supérieur'],
                        'proforma_recommendation' => 'Recommandé pour accompagner la vente de smartphones haut de gamme.',
                    ],
                    [
                        'level' => 'DEUXIEME_CHOIX', 'sku' => 'NJ-ENE-0210-D', 'name' => 'Chargeur 30W — Deuxième choix',
                        'purchase_price' => 4.1, 'sale_price' => 3500, 'moq' => 300,
                        'proforma_strengths' => ['Bon compromis prix / performance', 'Boîtier ignifugé'],
                        'proforma_weaknesses' => ['Sans câble inclus', 'Format légèrement plus encombrant'],
                        'proforma_recommendation' => 'Choix équilibré pour la vente au détail courante.',
                    ],
                    [
                        'level' => 'TROISIEME_CHOIX', 'sku' => 'NJ-ENE-0210-T', 'name' => 'Chargeur 30W — Troisième choix',
                        'purchase_price' => 2.6, 'sale_price' => 2500, 'moq' => 500,
                        'proforma_strengths' => ['Prix plancher', 'Idéal revente en gros'],
                        'proforma_weaknesses' => ['Charge non certifiée PD stricte', 'Garantie 6 mois'],
                        'proforma_recommendation' => 'Pour les commandes volume où le budget est le critère principal.',
                    ],
                ],
            ],
            [
                'reference' => 'NJ-OBJ-0055',
                'name' => 'Montre connectée Sport',
                'slug' => 'montre-connectee-sport',
                'category' => 'montres-connectees',
                'brand' => 'Pulse',
                'description' => 'Montre connectée avec cardiofréquencemètre, GPS, écran AMOLED 1,43".',
                'supplier' => 'shenzhen-tech',
                'variants' => [
                    [
                        'level' => 'PREMIER_CHOIX', 'sku' => 'NJ-OBJ-0055-P', 'name' => 'Montre Sport — Premier choix',
                        'purchase_price' => 22.0, 'sale_price' => 18900, 'moq' => 100,
                        'proforma_strengths' => ['GPS intégré', 'Écran AMOLED lumineux', 'Étanche 5 ATM'],
                        'proforma_weaknesses' => ['Autonomie 7 jours (usage GPS réduit à 20 h)'],
                        'proforma_recommendation' => 'Pour une offre différenciante face aux montres d\'entrée de gamme du marché.',
                    ],
                    [
                        'level' => 'DEUXIEME_CHOIX', 'sku' => 'NJ-OBJ-0055-D', 'name' => 'Montre Sport — Deuxième choix',
                        'purchase_price' => 13.5, 'sale_price' => 12500, 'moq' => 200,
                        'proforma_strengths' => ['Prix accessible', 'Suivi sommeil et fréquence cardiaque', 'Autonomie 10 jours'],
                        'proforma_weaknesses' => ['Pas de GPS (via téléphone uniquement)', 'Écran LCD'],
                        'proforma_recommendation' => 'Volume grand public : rotation rapide, marge correcte.',
                    ],
                ],
            ],
            [
                'reference' => 'NJ-BAG-0301',
                'name' => 'Sac à dos business anti-vol',
                'slug' => 'sac-a-dos-business-anti-vol',
                'category' => 'sacs',
                'brand' => 'Nomade',
                'description' => 'Sac à dos ordinateur 15,6", port USB, tissu déperlant, fermeture dissimulée.',
                'supplier' => 'yiwu-goods',
                'variants' => [
                    [
                        'level' => 'STANDARD', 'sku' => 'NJ-BAG-0301-S', 'name' => 'Sac à dos business — Standard',
                        'purchase_price' => 8.9, 'sale_price' => 9900, 'moq' => 100,
                        'proforma_strengths' => [], 'proforma_weaknesses' => [], 'proforma_recommendation' => null,
                    ],
                ],
            ],
            [
                'reference' => 'NJ-SOL-0402',
                'name' => 'Panneau solaire portable 200W',
                'slug' => 'panneau-solaire-portable-200w',
                'category' => 'materiel-agricole',
                'brand' => 'GreenPower',
                'description' => 'Panneau solaire pliable 200W, sorties USB et DC, idéal sites hors réseau.',
                'supplier' => 'ningbo-solar',
                'variants' => [
                    [
                        'level' => 'STANDARD', 'sku' => 'NJ-SOL-0402-S', 'name' => 'Panneau solaire 200W — Standard',
                        'purchase_price' => 63.0, 'sale_price' => 74900, 'moq' => 30,
                        'proforma_strengths' => [], 'proforma_weaknesses' => [], 'proforma_recommendation' => null,
                    ],
                ],
            ],
        ];

        $variants = [];
        foreach ($catalogue as $entry) {
            $product = Product::query()->updateOrCreate(
                ['reference' => $entry['reference']],
                [
                    'name' => $entry['name'],
                    'slug' => $entry['slug'],
                    'category_id' => $categoryId($entry['category']),
                    'description' => $entry['description'],
                    'brand' => $entry['brand'],
                    'status' => 'ACTIVE',
                    'is_sensitive' => false,
                    'created_by_user_id' => $admin->id,
                ],
            );

            $supplier = $suppliers[$entry['supplier']] ?? null;

            foreach ($entry['variants'] as $sortOrder => $v) {
                $variant = ProductVariant::query()->updateOrCreate(
                    ['sku' => $v['sku']],
                    [
                        'product_id' => $product->id,
                        'name' => $v['name'],
                        'level' => $v['level'],
                        'purchase_price' => $v['purchase_price'],
                        'purchase_currency_id' => $cny->id,
                        'sale_price' => $v['sale_price'],
                        'sale_currency_id' => $xaf->id,
                        'moq' => $v['moq'],
                        'proforma_strengths' => $v['proforma_strengths'],
                        'proforma_weaknesses' => $v['proforma_weaknesses'],
                        'proforma_recommendation' => $v['proforma_recommendation'],
                        'is_recommended' => $v['level'] === 'DEUXIEME_CHOIX',
                        'is_default' => $sortOrder === 0,
                        'is_active' => true,
                        'sort_order' => $sortOrder,
                    ],
                );

                if ($supplier) {
                    ProductSupplier::query()->updateOrCreate(
                        ['product_variant_id' => $variant->id, 'supplier_id' => $supplier->id],
                        [
                            'supplier_sku' => $v['sku'].'-CN',
                            'unit_price' => $v['purchase_price'],
                            'currency_id' => $cny->id,
                            'moq' => $v['moq'],
                            'lead_time_days' => 20,
                            'is_preferred' => true,
                            'last_quoted_at' => now()->subDays(10)->toDateString(),
                        ],
                    );
                }

                $variants[$v['sku']] = $variant;
            }
        }

        return $variants;
    }

    /**
     * @param  array<int, Client>  $clients
     * @param  array<string, ProductVariant>  $variants
     */
    private function seedSalesOrders(User $admin, array $clients, array $variants): void
    {
        $xaf = $this->xaf()->id;
        $today = now();

        // 1) Commande comparative (PRODUIT_UNIQUE_MULTI_CHOIX) : 3 options proposées,
        //    aucune encore choisie -> prête à émettre une proforma comparative dont les
        //    arguments sont pré-remplis depuis les fiches variantes.
        $this->makeOrder(
            reference: 'DEMO-CMP-0001',
            client: $clients[0],
            admin: $admin,
            type: 'PRODUIT_UNIQUE_MULTI_CHOIX',
            status: 'BROUILLON',
            currencyId: $xaf,
            orderDate: $today->copy()->subDays(3),
            lines: [
                ['sku' => 'NJ-ELE-0138-P', 'quantity' => 200, 'is_proposed_option' => true, 'is_selected' => false],
                ['sku' => 'NJ-ELE-0138-D', 'quantity' => 200, 'is_proposed_option' => true, 'is_selected' => false],
                ['sku' => 'NJ-ELE-0138-T', 'quantity' => 200, 'is_proposed_option' => true, 'is_selected' => false],
            ],
            variants: $variants,
            transportMode: 'AERIEN_STANDARD',
            estimatedWeightKg: 55,
        );

        // 2) Deuxième commande comparative (chargeurs), pour tester le pré-remplissage
        //    sur un autre produit.
        $this->makeOrder(
            reference: 'DEMO-CMP-0002',
            client: $clients[4],
            admin: $admin,
            type: 'PRODUIT_UNIQUE_MULTI_CHOIX',
            status: 'BROUILLON',
            currencyId: $xaf,
            orderDate: $today->copy()->subDays(2),
            lines: [
                ['sku' => 'NJ-ENE-0210-P', 'quantity' => 300, 'is_proposed_option' => true, 'is_selected' => false],
                ['sku' => 'NJ-ENE-0210-D', 'quantity' => 300, 'is_proposed_option' => true, 'is_selected' => false],
                ['sku' => 'NJ-ENE-0210-T', 'quantity' => 300, 'is_proposed_option' => true, 'is_selected' => false],
            ],
            variants: $variants,
            transportMode: 'AERIEN_STANDARD',
            estimatedWeightKg: 40,
        );

        // 3) Commande multi-produits confirmée (lignes retenues).
        $this->makeOrder(
            reference: 'DEMO-MUL-0003',
            client: $clients[1],
            admin: $admin,
            type: 'MULTI_PRODUITS',
            status: 'CONFIRMEE',
            currencyId: $xaf,
            orderDate: $today->copy()->subDays(9),
            lines: [
                ['sku' => 'NJ-OBJ-0055-D', 'quantity' => 120, 'is_proposed_option' => false, 'is_selected' => true],
                ['sku' => 'NJ-BAG-0301-S', 'quantity' => 80, 'is_proposed_option' => false, 'is_selected' => true],
            ],
            variants: $variants,
            transportMode: 'MARITIME',
            estimatedVolumeCbm: 3.2,
        );

        // 4) Prestation de service.
        $order = $this->makeOrder(
            reference: 'DEMO-SRV-0004',
            client: $clients[3],
            admin: $admin,
            type: 'PRESTATION_SERVICE',
            status: 'PROFORMA_ENVOYEE',
            currencyId: $xaf,
            orderDate: $today->copy()->subDays(6),
            lines: [],
            variants: $variants,
            transportMode: 'NON_APPLICABLE',
        );
        $order->items()->delete();
        $order->items()->create([
            'item_type' => 'SERVICE',
            'label' => 'Accompagnement sourcing + contrôle qualité usine (2 jours)',
            'quantity' => 1,
            'unit_price' => 350000,
            'discount_amount' => 0,
            'subtotal' => 350000,
            'is_proposed_option' => false,
            'is_selected' => true,
            'sort_order' => 0,
        ]);
        $order->update(['subtotal_amount' => 350000, 'total_amount' => 350000]);
    }

    /** @param array<string, ProductVariant> $variants */
    private function makeOrder(
        string $reference,
        Client $client,
        User $admin,
        string $type,
        string $status,
        int $currencyId,
        Carbon $orderDate,
        array $lines,
        array $variants,
        string $transportMode = 'NON_APPLICABLE',
        ?float $estimatedWeightKg = null,
        ?float $estimatedVolumeCbm = null,
    ): SalesOrder {
        $order = SalesOrder::query()->updateOrCreate(
            ['reference' => $reference],
            [
                'client_id' => $client->id,
                'type' => $type,
                'status' => $status,
                'currency_id' => $currencyId,
                'billing_mode' => 'COMMISSION_VISIBLE',
                'commission_type' => 'FORFAIT',
                'commission_amount' => 0,
                'discount_amount' => 0,
                'subtotal_amount' => 0,
                'total_amount' => 0,
                'transport_mode' => $transportMode,
                'estimated_weight_kg' => $estimatedWeightKg,
                'estimated_volume_cbm' => $estimatedVolumeCbm,
                'order_date' => $orderDate->toDateString(),
                'validity_days' => 7,
                'valid_until' => $orderDate->copy()->addDays(7)->toDateString(),
                'created_by_user_id' => $admin->id,
            ],
        );

        $order->items()->delete();

        $subtotal = 0.0;
        foreach ($lines as $sortOrder => $line) {
            $variant = $variants[$line['sku']] ?? null;
            if (! $variant) {
                continue;
            }
            $unitPrice = (float) ($variant->sale_price ?? 0);
            $lineSubtotal = $unitPrice * $line['quantity'];
            $subtotal += $lineSubtotal;

            $order->items()->create([
                'item_type' => 'PRODUIT',
                'product_variant_id' => $variant->id,
                'label' => $variant->name,
                'quantity' => $line['quantity'],
                'unit_price' => $unitPrice,
                'discount_amount' => 0,
                'subtotal' => $lineSubtotal,
                'is_proposed_option' => $line['is_proposed_option'],
                'is_selected' => $line['is_selected'],
                'sort_order' => $sortOrder,
            ]);
        }

        $order->update(['subtotal_amount' => $subtotal, 'total_amount' => $subtotal]);

        return $order;
    }
}
