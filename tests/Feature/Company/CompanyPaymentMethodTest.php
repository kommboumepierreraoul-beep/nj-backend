<?php

namespace Tests\Feature\Company;

use App\Enums\PaymentMethodType;
use App\Models\CompanyPaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Ecran "Parametres -> Entreprise -> Moyens de paiement"
 * (App\Http\Controllers\Company\CompanyPaymentMethodController).
 */
class CompanyPaymentMethodTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_the_four_seeded_methods_are_listed_in_sort_order(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $response = $this->getJson('/api/company-payment-methods', $headers)->assertOk();

        $this->assertCount(4, $response->json('data'));
        $this->assertSame('Orange Money (Cameroun)', $response->json('data.0.label'));
    }

    public function test_admin_can_create_a_payment_method(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/company-payment-methods', [
            'label' => 'Wave Senegal',
            'method_type' => PaymentMethodType::MOBILE_MONEY->value,
            'account_number' => '77 123 45 67',
            'sort_order' => 5,
        ], $headers)->assertCreated()->assertJsonPath('data.label', 'Wave Senegal');

        $this->assertDatabaseHas('company_payment_methods', ['label' => 'Wave Senegal']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'company_payment_method.created',
            'entity_type' => 'CompanyPaymentMethod',
        ]);
    }

    public function test_invalid_method_type_is_rejected(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/company-payment-methods', [
            'label' => 'Bad', 'method_type' => 'CRYPTO',
        ], $headers)->assertStatus(422);
    }

    public function test_admin_can_hide_a_method_from_documents(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $method = CompanyPaymentMethod::factory()->create(['show_on_documents' => true]);

        $this->putJson("/api/company-payment-methods/{$method->id}", [
            'show_on_documents' => false,
        ], $headers)->assertOk()->assertJsonPath('data.show_on_documents', false);

        $this->assertDatabaseHas('audit_logs', ['action' => 'company_payment_method.updated']);
    }

    public function test_admin_can_delete_a_method(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $method = CompanyPaymentMethod::factory()->create();

        $this->deleteJson("/api/company-payment-methods/{$method->id}", [], $headers)->assertOk();

        $this->assertDatabaseMissing('company_payment_methods', ['id' => $method->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'company_payment_method.deleted']);
    }

    public function test_view_permission_is_required_to_list(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('company_settings.view');

        $this->getJson('/api/company-payment-methods', $headers)->assertForbidden();
    }

    public function test_manage_permission_is_required_to_create(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('company_settings.manage');

        $this->postJson('/api/company-payment-methods', [
            'label' => 'Nope', 'method_type' => PaymentMethodType::CASH->value,
        ], $headers)->assertForbidden();
    }
}
