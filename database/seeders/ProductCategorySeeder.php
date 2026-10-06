<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ProductCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Catalogue de catégories inspiré d'une architecture e-commerce
     * moderne (type Shopify).
     *
     * Structure :
     * Catégorie
     *   └── Sous-catégorie
     *         └── Sous-sous-catégorie
     *
     * Le système utilise uniquement parent_id, ce qui permet une
     * profondeur illimitée.
     */
    private const CATEGORIES = [

        /*
        |--------------------------------------------------------------------------
        | ÉLECTRONIQUE
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Électronique',
            'slug' => 'electronique',
            'description' => 'Appareils électroniques, équipements et technologies.',
            'sort_order' => 1,
            'children' => [

                [
                    'name' => 'Téléphones et smartphones',
                    'slug' => 'telephones-smartphones',
                    'children' => [
                        ['name' => 'Smartphones', 'slug' => 'smartphones'],
                        ['name' => 'Téléphones classiques', 'slug' => 'telephones-classiques'],
                        ['name' => 'Téléphones reconditionnés', 'slug' => 'telephones-reconditionnes'],
                        ['name' => 'Accessoires smartphones', 'slug' => 'accessoires-smartphones'],
                    ],
                ],

                [
                    'name' => 'Ordinateurs',
                    'slug' => 'ordinateurs',
                    'children' => [
                        ['name' => 'Ordinateurs portables', 'slug' => 'ordinateurs-portables'],
                        ['name' => 'Ordinateurs de bureau', 'slug' => 'ordinateurs-bureau'],
                        ['name' => 'Mac', 'slug' => 'mac'],
                        ['name' => 'PC Gaming', 'slug' => 'pc-gaming'],
                        ['name' => 'Mini PC', 'slug' => 'mini-pc'],
                    ],
                ],

                [
                    'name' => 'Tablettes',
                    'slug' => 'tablettes',
                    'children' => [
                        ['name' => 'Tablettes Android', 'slug' => 'tablettes-android'],
                        ['name' => 'iPad', 'slug' => 'ipad'],
                        ['name' => 'Tablettes graphiques', 'slug' => 'tablettes-graphiques'],
                    ],
                ],

                [
                    'name' => 'Télévision et vidéo',
                    'slug' => 'television-video',
                    'children' => [
                        ['name' => 'Smart TV', 'slug' => 'smart-tv'],
                        ['name' => 'Téléviseurs LED', 'slug' => 'televiseurs-led'],
                        ['name' => 'Projecteurs', 'slug' => 'projecteurs'],
                        ['name' => 'Écrans', 'slug' => 'ecrans'],
                        ['name' => 'Décodeurs TV', 'slug' => 'decodeurs-tv'],
                    ],
                ],

                [
                    'name' => 'Audio',
                    'slug' => 'audio',
                    'children' => [
                        ['name' => 'Écouteurs', 'slug' => 'ecouteurs'],
                        ['name' => 'Casques audio', 'slug' => 'casques-audio'],
                        ['name' => 'Enceintes Bluetooth', 'slug' => 'enceintes-bluetooth'],
                        ['name' => 'Enceintes professionnelles', 'slug' => 'enceintes-professionnelles'],
                        ['name' => 'Microphones', 'slug' => 'microphones'],
                        ['name' => 'Home cinéma', 'slug' => 'home-cinema'],
                    ],
                ],

                [
                    'name' => 'Appareils photo',
                    'slug' => 'appareils-photo',
                    'children' => [
                        ['name' => 'Appareils photo numériques', 'slug' => 'appareils-photo-numeriques'],
                        ['name' => 'Caméras vidéo', 'slug' => 'cameras-video'],
                        ['name' => 'Objectifs', 'slug' => 'objectifs'],
                        ['name' => 'Trépieds', 'slug' => 'trepieds'],
                        ['name' => 'Accessoires photo', 'slug' => 'accessoires-photo'],
                    ],
                ],

                [
                    'name' => 'Gaming',
                    'slug' => 'gaming',
                    'children' => [
                        ['name' => 'Consoles', 'slug' => 'consoles'],
                        ['name' => 'Jeux vidéo', 'slug' => 'jeux-video'],
                        ['name' => 'Manettes', 'slug' => 'manettes'],
                        ['name' => 'Claviers gaming', 'slug' => 'claviers-gaming'],
                        ['name' => 'Souris gaming', 'slug' => 'souris-gaming'],
                        ['name' => 'Casques gaming', 'slug' => 'casques-gaming'],
                        ['name' => 'Chaises gaming', 'slug' => 'chaises-gaming'],
                    ],
                ],

                [
                    'name' => 'Objets connectés',
                    'slug' => 'objets-connectes',
                    'children' => [
                        ['name' => 'Montres connectées', 'slug' => 'montres-connectees'],
                        ['name' => 'Bracelets connectés', 'slug' => 'bracelets-connectes'],
                        ['name' => 'Maison connectée', 'slug' => 'maison-connectee'],
                        ['name' => 'Caméras de surveillance', 'slug' => 'cameras-surveillance'],
                    ],
                ],

                [
                    'name' => 'Charge et énergie',
                    'slug' => 'charge-energie',
                    'children' => [
                        ['name' => 'Chargeurs', 'slug' => 'chargeurs'],
                        ['name' => 'Batteries externes', 'slug' => 'batteries-externes'],
                        ['name' => 'Câbles', 'slug' => 'cables'],
                        ['name' => 'Adaptateurs', 'slug' => 'adaptateurs'],
                        ['name' => 'Multiprises', 'slug' => 'multiprises'],
                        ['name' => 'Onduleurs', 'slug' => 'onduleurs'],
                    ],
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | MODE
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Mode',
            'slug' => 'mode',
            'description' => 'Vêtements, chaussures et accessoires de mode.',
            'sort_order' => 2,
            'children' => [

                [
                    'name' => 'Vêtements homme',
                    'slug' => 'vetements-homme',
                    'children' => [
                        ['name' => 'T-shirts', 'slug' => 't-shirts-homme'],
                        ['name' => 'Chemises', 'slug' => 'chemises-homme'],
                        ['name' => 'Pantalons', 'slug' => 'pantalons-homme'],
                        ['name' => 'Jeans', 'slug' => 'jeans-homme'],
                        ['name' => 'Vestes et manteaux', 'slug' => 'vestes-manteaux-homme'],
                        ['name' => 'Costumes', 'slug' => 'costumes-homme'],
                        ['name' => 'Vêtements de sport', 'slug' => 'vetements-sport-homme'],
                    ],
                ],

                [
                    'name' => 'Vêtements femme',
                    'slug' => 'vetements-femme',
                    'children' => [
                        ['name' => 'Robes', 'slug' => 'robes'],
                        ['name' => 'Tops et chemisiers', 'slug' => 'tops-chemisiers'],
                        ['name' => 'Pantalons', 'slug' => 'pantalons-femme'],
                        ['name' => 'Jeans', 'slug' => 'jeans-femme'],
                        ['name' => 'Jupes', 'slug' => 'jupes'],
                        ['name' => 'Vestes et manteaux', 'slug' => 'vestes-manteaux-femme'],
                        ['name' => 'Vêtements de sport', 'slug' => 'vetements-sport-femme'],
                    ],
                ],

                [
                    'name' => 'Chaussures',
                    'slug' => 'chaussures',
                    'children' => [
                        ['name' => 'Chaussures homme', 'slug' => 'chaussures-homme'],
                        ['name' => 'Chaussures femme', 'slug' => 'chaussures-femme'],
                        ['name' => 'Chaussures enfant', 'slug' => 'chaussures-enfant'],
                        ['name' => 'Baskets', 'slug' => 'baskets'],
                        ['name' => 'Sandales', 'slug' => 'sandales'],
                        ['name' => 'Chaussures de sport', 'slug' => 'chaussures-sport'],
                    ],
                ],

                [
                    'name' => 'Accessoires de mode',
                    'slug' => 'accessoires-mode',
                    'children' => [
                        ['name' => 'Sacs', 'slug' => 'sacs'],
                        ['name' => 'Portefeuilles', 'slug' => 'portefeuilles'],
                        ['name' => 'Ceintures', 'slug' => 'ceintures'],
                        ['name' => 'Lunettes', 'slug' => 'lunettes'],
                        ['name' => 'Montres', 'slug' => 'montres'],
                        ['name' => 'Bijoux', 'slug' => 'bijoux'],
                        ['name' => 'Casquettes et chapeaux', 'slug' => 'casquettes-chapeaux'],
                    ],
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | MAISON
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Maison et décoration',
            'slug' => 'maison-decoration',
            'description' => 'Produits pour la maison, mobilier et décoration.',
            'sort_order' => 3,
            'children' => [

                [
                    'name' => 'Mobilier',
                    'slug' => 'mobilier',
                    'children' => [
                        ['name' => 'Canapés', 'slug' => 'canapes'],
                        ['name' => 'Lits', 'slug' => 'lits'],
                        ['name' => 'Tables', 'slug' => 'tables'],
                        ['name' => 'Chaises', 'slug' => 'chaises'],
                        ['name' => 'Armoires', 'slug' => 'armoires'],
                        ['name' => 'Bureaux', 'slug' => 'bureaux'],
                    ],
                ],

                [
                    'name' => 'Cuisine',
                    'slug' => 'cuisine',
                    'children' => [
                        ['name' => 'Ustensiles de cuisine', 'slug' => 'ustensiles-cuisine'],
                        ['name' => 'Vaisselle', 'slug' => 'vaisselle'],
                        ['name' => 'Appareils de cuisine', 'slug' => 'appareils-cuisine'],
                        ['name' => 'Rangement cuisine', 'slug' => 'rangement-cuisine'],
                    ],
                ],

                [
                    'name' => 'Décoration',
                    'slug' => 'decoration',
                    'children' => [
                        ['name' => 'Tableaux', 'slug' => 'tableaux'],
                        ['name' => 'Miroirs', 'slug' => 'miroirs'],
                        ['name' => 'Lampes', 'slug' => 'lampes'],
                        ['name' => 'Tapis', 'slug' => 'tapis'],
                        ['name' => 'Rideaux', 'slug' => 'rideaux'],
                    ],
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | BEAUTÉ
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Beauté et soins',
            'slug' => 'beaute-soins',
            'description' => 'Cosmétiques, soins personnels et parfums.',
            'sort_order' => 4,
            'children' => [

                [
                    'name' => 'Soins de la peau',
                    'slug' => 'soins-peau',
                    'children' => [
                        ['name' => 'Crèmes', 'slug' => 'cremes'],
                        ['name' => 'Nettoyants', 'slug' => 'nettoyants'],
                        ['name' => 'Sérums', 'slug' => 'serums'],
                        ['name' => 'Masques', 'slug' => 'masques'],
                    ],
                ],

                [
                    'name' => 'Maquillage',
                    'slug' => 'maquillage',
                    'children' => [
                        ['name' => 'Rouges à lèvres', 'slug' => 'rouges-levres'],
                        ['name' => 'Fond de teint', 'slug' => 'fond-de-teint'],
                        ['name' => 'Mascara', 'slug' => 'mascara'],
                        ['name' => 'Palettes', 'slug' => 'palettes-maquillage'],
                    ],
                ],

                [
                    'name' => 'Parfums',
                    'slug' => 'parfums',
                    'children' => [
                        ['name' => 'Parfums homme', 'slug' => 'parfums-homme'],
                        ['name' => 'Parfums femme', 'slug' => 'parfums-femme'],
                        ['name' => 'Parfums unisexes', 'slug' => 'parfums-unisexes'],
                    ],
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | SPORT
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Sports et loisirs',
            'slug' => 'sports-loisirs',
            'description' => 'Équipements sportifs et articles de loisirs.',
            'sort_order' => 5,
            'children' => [

                [
                    'name' => 'Fitness',
                    'slug' => 'fitness',
                    'children' => [
                        ['name' => 'Haltères', 'slug' => 'halteres'],
                        ['name' => 'Tapis de sport', 'slug' => 'tapis-sport'],
                        ['name' => 'Élastiques', 'slug' => 'elastiques-fitness'],
                        ['name' => 'Machines de fitness', 'slug' => 'machines-fitness'],
                    ],
                ],

                [
                    'name' => 'Football',
                    'slug' => 'football',
                    'children' => [
                        ['name' => 'Ballons', 'slug' => 'ballons-football'],
                        ['name' => 'Maillots', 'slug' => 'maillots-football'],
                        ['name' => 'Chaussures', 'slug' => 'chaussures-football'],
                        ['name' => 'Équipements', 'slug' => 'equipements-football'],
                    ],
                ],

                [
                    'name' => 'Cyclisme',
                    'slug' => 'cyclisme',
                    'children' => [
                        ['name' => 'Vélos', 'slug' => 'velos'],
                        ['name' => 'Casques', 'slug' => 'casques-cyclisme'],
                        ['name' => 'Accessoires vélo', 'slug' => 'accessoires-velo'],
                    ],
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | BÉBÉ ET ENFANT
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Bébé et enfant',
            'slug' => 'bebe-enfant',
            'description' => 'Produits pour bébés, enfants et parents.',
            'sort_order' => 6,
            'children' => [

                [
                    'name' => 'Vêtements enfant',
                    'slug' => 'vetements-enfant',
                    'children' => [
                        ['name' => 'Bébé fille', 'slug' => 'vetements-bebe-fille'],
                        ['name' => 'Bébé garçon', 'slug' => 'vetements-bebe-garcon'],
                        ['name' => 'Fille', 'slug' => 'vetements-fille'],
                        ['name' => 'Garçon', 'slug' => 'vetements-garcon'],
                    ],
                ],

                [
                    'name' => 'Jouets',
                    'slug' => 'jouets',
                    'children' => [
                        ['name' => 'Jeux éducatifs', 'slug' => 'jeux-educatifs'],
                        ['name' => 'Jeux de construction', 'slug' => 'jeux-construction'],
                        ['name' => 'Poupées', 'slug' => 'poupees'],
                        ['name' => 'Véhicules jouets', 'slug' => 'vehicules-jouets'],
                    ],
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | ÉLECTROMÉNAGER
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Électroménager',
            'slug' => 'electromenager',
            'description' => 'Appareils électroménagers pour la maison.',
            'sort_order' => 7,
            'children' => [

                [
                    'name' => 'Gros électroménager',
                    'slug' => 'gros-electromenager',
                    'children' => [
                        ['name' => 'Réfrigérateurs', 'slug' => 'refrigerateurs'],
                        ['name' => 'Congélateurs', 'slug' => 'congelateurs'],
                        ['name' => 'Lave-linge', 'slug' => 'lave-linge'],
                        ['name' => 'Climatiseurs', 'slug' => 'climatiseurs'],
                    ],
                ],

                [
                    'name' => 'Petit électroménager',
                    'slug' => 'petit-electromenager',
                    'children' => [
                        ['name' => 'Mixeurs', 'slug' => 'mixeurs'],
                        ['name' => 'Blenders', 'slug' => 'blenders'],
                        ['name' => 'Fours', 'slug' => 'fours'],
                        ['name' => 'Bouilloires', 'slug' => 'bouilloires'],
                        ['name' => 'Cafetières', 'slug' => 'cafetiere'],
                    ],
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | AUTO / MOTO
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Auto et moto',
            'slug' => 'auto-moto',
            'description' => 'Pièces, accessoires et équipements automobiles.',
            'sort_order' => 8,
            'children' => [

                [
                    'name' => 'Pièces automobiles',
                    'slug' => 'pieces-automobiles',
                    'children' => [
                        ['name' => 'Batteries', 'slug' => 'batteries-auto'],
                        ['name' => 'Pneus', 'slug' => 'pneus'],
                        ['name' => 'Freinage', 'slug' => 'freinage'],
                        ['name' => 'Filtres', 'slug' => 'filtres-auto'],
                    ],
                ],

                [
                    'name' => 'Accessoires auto',
                    'slug' => 'accessoires-auto',
                    'children' => [
                        ['name' => 'Autoradios', 'slug' => 'autoradios'],
                        ['name' => 'GPS', 'slug' => 'gps-auto'],
                        ['name' => 'Caméras embarquées', 'slug' => 'dashcams'],
                        ['name' => 'Housses', 'slug' => 'housses-auto'],
                    ],
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | BUREAUTIQUE
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Bureautique',
            'slug' => 'bureautique',
            'description' => 'Matériel informatique, fournitures et équipements de bureau.',
            'sort_order' => 9,
            'children' => [

                [
                    'name' => 'Impression',
                    'slug' => 'impression',
                    'children' => [
                        ['name' => 'Imprimantes', 'slug' => 'imprimantes'],
                        ['name' => 'Scanners', 'slug' => 'scanners'],
                        ['name' => 'Cartouches', 'slug' => 'cartouches'],
                        ['name' => 'Toners', 'slug' => 'toners'],
                    ],
                ],

                [
                    'name' => 'Fournitures',
                    'slug' => 'fournitures-bureau',
                    'children' => [
                        ['name' => 'Papeterie', 'slug' => 'papeterie'],
                        ['name' => 'Stylos', 'slug' => 'stylos'],
                        ['name' => 'Cahiers', 'slug' => 'cahiers'],
                        ['name' => 'Classeurs', 'slug' => 'classeurs'],
                    ],
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | AGRICULTURE
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Agriculture',
            'slug' => 'agriculture',
            'description' => 'Produits, équipements et fournitures agricoles.',
            'sort_order' => 10,
            'children' => [

                [
                    'name' => 'Matériel agricole',
                    'slug' => 'materiel-agricole',
                    'children' => [
                        ['name' => 'Machines agricoles', 'slug' => 'machines-agricoles'],
                        ['name' => 'Motoculteurs', 'slug' => 'motoculteurs'],
                        ['name' => 'Pulvérisateurs', 'slug' => 'pulverisateurs'],
                        ['name' => 'Outils agricoles', 'slug' => 'outils-agricoles'],
                    ],
                ],

                [
                    'name' => 'Semences',
                    'slug' => 'semences',
                    'children' => [
                        ['name' => 'Maïs', 'slug' => 'semences-mais'],
                        ['name' => 'Tomate', 'slug' => 'semences-tomate'],
                        ['name' => 'Haricot', 'slug' => 'semences-haricot'],
                        ['name' => 'Légumes', 'slug' => 'semences-legumes'],
                    ],
                ],

                [
                    'name' => 'Élevage',
                    'slug' => 'elevage',
                    'children' => [
                        ['name' => 'Alimentation animale', 'slug' => 'alimentation-animale'],
                        ['name' => 'Matériel d’élevage', 'slug' => 'materiel-elevage'],
                        ['name' => 'Équipements avicoles', 'slug' => 'equipements-avicoles'],
                    ],
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | OUTILLAGE
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Outillage et bricolage',
            'slug' => 'outillage-bricolage',
            'description' => 'Outils, équipements et matériel de bricolage.',
            'sort_order' => 11,
            'children' => [

                [
                    'name' => 'Outillage à main',
                    'slug' => 'outillage-main',
                    'children' => [
                        ['name' => 'Marteaux', 'slug' => 'marteaux'],
                        ['name' => 'Tournevis', 'slug' => 'tournevis'],
                        ['name' => 'Pinces', 'slug' => 'pinces'],
                        ['name' => 'Clés', 'slug' => 'cles'],
                    ],
                ],

                [
                    'name' => 'Outillage électrique',
                    'slug' => 'outillage-electrique',
                    'children' => [
                        ['name' => 'Perceuses', 'slug' => 'perceuses'],
                        ['name' => 'Meuleuses', 'slug' => 'meuleuses'],
                        ['name' => 'Scies électriques', 'slug' => 'scies-electriques'],
                    ],
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | ALIMENTATION
        |--------------------------------------------------------------------------
        */

        [
            'name' => 'Alimentation et boissons',
            'slug' => 'alimentation-boissons',
            'description' => 'Produits alimentaires et boissons.',
            'sort_order' => 12,
            'children' => [

                [
                    'name' => 'Épicerie',
                    'slug' => 'epicerie',
                    'children' => [
                        ['name' => 'Riz', 'slug' => 'riz'],
                        ['name' => 'Pâtes', 'slug' => 'pates'],
                        ['name' => 'Huiles', 'slug' => 'huiles'],
                        ['name' => 'Conserves', 'slug' => 'conserves'],
                    ],
                ],

                [
                    'name' => 'Produits locaux',
                    'slug' => 'produits-locaux',
                    'children' => [
                        ['name' => 'Miel', 'slug' => 'miel'],
                        ['name' => 'Café', 'slug' => 'cafe'],
                        ['name' => 'Cacao', 'slug' => 'cacao'],
                        ['name' => 'Épices', 'slug' => 'epices'],
                    ],
                ],
            ],
        ],
    ];

    /**
     * Crée récursivement une catégorie et ses enfants.
     */
    private function createCategory(array $data, ?int $parentId = null): ProductCategory
    {
        $category = ProductCategory::query()->updateOrCreate(
            ['slug' => $data['slug']],
            [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'image_path' => $data['image_path'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
                'parent_id' => $parentId,
            ]
        );

        foreach ($data['children'] ?? [] as $index => $child) {
            $child['sort_order'] = $child['sort_order'] ?? ($index + 1);

            $this->createCategory(
                $child,
                $category->id
            );
        }

        return $category;
    }

    public function run(): void
    {
        foreach (self::CATEGORIES as $category) {
            $this->createCategory($category);
        }
    }
}