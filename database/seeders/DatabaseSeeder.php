<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Mot de passe par defaut documente dans .env.example : ne doit jamais
     * servir a proteger le compte d'amorcage en production.
     */
    private const INSECURE_DEFAULT_ADMIN_PASSWORD = 'ChangeMe!12345';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = config('auth.default_admin');

        if (app()->isProduction() && $admin['password'] === self::INSECURE_DEFAULT_ADMIN_PASSWORD) {
            throw new RuntimeException(
                "DEFAULT_ADMIN_PASSWORD doit etre defini explicitement en production : la valeur par defaut '".self::INSECURE_DEFAULT_ADMIN_PASSWORD."' est refusee.",
            );
        }

        Validator::make($admin, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', new Enum(UserRole::class)],
        ])->validate();

        // Ce compte sert uniquement a l'amorcage du systeme: il n'est pas recree si l'email existe deja.
        User::query()->firstOrCreate(
            ['email' => $admin['email']],
            [
                'name' => $admin['name'],
                'full_name' => $admin['name'],
                'password' => $admin['password'],
                'role' => $admin['role'],
                'is_active' => true,
                'must_change_password' => true,
            ],
        );

        // Referentiels systeme (pays, devises, unites de mesure, categories de
        // produits) : chacun de ces seeders reste par ailleurs lancable
        // isolement (voir son docblock, ex. `php artisan db:seed
        // --class=CountrySeeder`), mais un simple `php artisan db:seed` doit a
        // lui seul amorcer tout le referentiel de base necessaire aux modules
        // Produits/Fournisseurs/Clients/Commandes plutot que d'exiger 4
        // commandes separees. Idempotents (updateOrCreate), donc sans risque
        // a rejouer sur une base deja seedee.
        $this->call([
            CountrySeeder::class,
            CurrencySeeder::class,
            UnitOfMeasureSeeder::class,
            ProductCategorySeeder::class,
        ]);

        // Donnees de demonstration (equipe fictive, catalogue, clients, fournisseurs,
        // commandes dont une commande comparative prete a emettre une proforma) : hors
        // production uniquement, pour que `php artisan migrate:fresh --seed` donne une
        // base immediatement testable. Egalement lancable seul :
        // `php artisan db:seed --class=DemoDataSeeder`. Idempotent, et son propre
        // garde-fou re-verifie `isProduction()` (defense en profondeur).
        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
