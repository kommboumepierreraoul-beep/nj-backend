<?php

namespace Database\Factories;

use App\Enums\BillingMode;
use App\Enums\ClientLanguage;
use App\Enums\ClientStatus;
use App\Enums\ClientType;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_type' => ClientType::PARTICULIER->value,
            'full_name' => fake()->name(),
            'preferred_language' => ClientLanguage::FR->value,
            'billing_mode' => BillingMode::COMMISSION_VISIBLE->value,
            'has_custom_commission' => false,
            'status' => ClientStatus::ACTIF->value,
            'created_by_user_id' => User::factory(),
        ];
    }
}
