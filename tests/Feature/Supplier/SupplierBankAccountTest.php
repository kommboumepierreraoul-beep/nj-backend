<?php

namespace Tests\Feature\Supplier;

use App\Enums\SupplierPaymentMethod;
use App\Models\Currency;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class SupplierBankAccountTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_create_a_bank_account_for_a_supplier(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $currency = Currency::factory()->create();

        $this->postJson("/api/suppliers/{$supplier->id}/bank-accounts", [
            'method' => SupplierPaymentMethod::ALIPAY->value,
            'account_name' => 'Shenzhen Trading Co',
            'account_number' => 'CN1234567890',
            'currency_id' => $currency->id,
            'is_default' => true,
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('supplier_bank_accounts', ['supplier_id' => $supplier->id, 'account_number' => 'CN1234567890']);
    }

    public function test_setting_an_account_as_default_unsets_the_previous_default(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $currency = Currency::factory()->create();
        $first = $supplier->bankAccounts()->create([
            'method' => SupplierPaymentMethod::WECHAT_PAY->value,
            'account_name' => 'Compte A',
            'account_number' => '111',
            'currency_id' => $currency->id,
            'is_default' => true,
        ]);

        $created = $this->postJson("/api/suppliers/{$supplier->id}/bank-accounts", [
            'method' => SupplierPaymentMethod::BANK_TRANSFER_CNY->value,
            'account_name' => 'Compte B',
            'account_number' => '222',
            'currency_id' => $currency->id,
            'is_default' => true,
        ], $headers)->json('data.id');

        $this->assertDatabaseHas('supplier_bank_accounts', ['id' => $created, 'is_default' => true]);
        $this->assertDatabaseHas('supplier_bank_accounts', ['id' => $first->id, 'is_default' => false]);
    }

    public function test_admin_can_list_update_and_delete_a_bank_account(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $currency = Currency::factory()->create();
        $account = $supplier->bankAccounts()->create([
            'method' => SupplierPaymentMethod::CASH_CHINA->value,
            'account_name' => 'Compte C',
            'account_number' => '333',
            'currency_id' => $currency->id,
        ]);

        $this->getJson("/api/suppliers/{$supplier->id}/bank-accounts", $headers)->assertOk();

        $this->putJson("/api/suppliers/{$supplier->id}/bank-accounts/{$account->id}", [
            'account_name' => 'Compte C renomme',
        ], $headers)->assertOk();

        $this->deleteJson("/api/suppliers/{$supplier->id}/bank-accounts/{$account->id}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('supplier_bank_accounts', ['id' => $account->id]);
    }
}
