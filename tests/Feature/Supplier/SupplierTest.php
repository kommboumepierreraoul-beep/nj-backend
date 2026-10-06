<?php

namespace Tests\Feature\Supplier;

use App\Enums\SupplierVerificationMethod;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_guest_cannot_list_suppliers(): void
    {
        $this->getJson('/api/suppliers')->assertUnauthorized();
    }

    public function test_admin_can_create_a_supplier(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/suppliers', [
            'company_name' => 'Shenzhen Trading Co',
            'contact_name' => 'Li Wei',
            'email' => 'li.wei@shenzhen-trading.test',
        ], $headers)->assertCreated()->assertJsonPath('data.company_name', 'Shenzhen Trading Co');

        $this->assertDatabaseHas('suppliers', [
            'company_name' => 'Shenzhen Trading Co',
            'created_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_show_and_update_a_supplier(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create(['company_name' => 'Ancien nom']);

        $this->getJson("/api/suppliers/{$supplier->id}", $headers)->assertOk();

        $this->putJson("/api/suppliers/{$supplier->id}", [
            'company_name' => 'Nouveau nom',
        ], $headers)->assertOk()->assertJsonPath('data.company_name', 'Nouveau nom');
    }

    public function test_admin_can_verify_a_supplier(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();

        $this->postJson("/api/suppliers/{$supplier->id}/verify", [
            'verification_method' => SupplierVerificationMethod::VIDEO_CALL->value,
        ], $headers)->assertOk()->assertJsonPath('data.is_verified', true);

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'is_verified' => true]);
    }

    public function test_admin_can_blacklist_and_unblacklist_a_supplier(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();

        $this->postJson("/api/suppliers/{$supplier->id}/blacklist", [
            'is_blacklisted' => true,
            'blacklist_reason' => 'Produits non conformes',
        ], $headers)->assertOk();
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'is_blacklisted' => true]);

        $this->postJson("/api/suppliers/{$supplier->id}/blacklist", [
            'is_blacklisted' => false,
        ], $headers)->assertOk();
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'is_blacklisted' => false, 'blacklist_reason' => null]);
    }

    public function test_blacklisting_a_supplier_requires_a_reason(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();

        $this->postJson("/api/suppliers/{$supplier->id}/blacklist", [
            'is_blacklisted' => true,
        ], $headers)->assertStatus(422)->assertJsonValidationErrors(['blacklist_reason']);
    }

    public function test_admin_can_delete_a_supplier_which_is_soft_deleted(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();

        $this->deleteJson("/api/suppliers/{$supplier->id}", [], $headers)->assertOk();

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_manage_permission_is_required_to_update_a_supplier(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('suppliers.manage');
        $supplier = Supplier::factory()->create();

        $this->putJson("/api/suppliers/{$supplier->id}", ['company_name' => 'X'], $headers)->assertForbidden();
    }
}
