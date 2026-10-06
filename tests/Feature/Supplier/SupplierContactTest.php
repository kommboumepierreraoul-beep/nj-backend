<?php

namespace Tests\Feature\Supplier;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class SupplierContactTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_create_a_contact_for_a_supplier(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();

        $this->postJson("/api/suppliers/{$supplier->id}/contacts", [
            'full_name' => 'Zhang Min',
            'role_title' => 'Sales Manager',
            'is_primary' => true,
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('supplier_contacts', ['supplier_id' => $supplier->id, 'full_name' => 'Zhang Min']);
    }

    public function test_setting_a_contact_as_primary_unsets_the_previous_primary_contact(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $first = $supplier->contacts()->create(['full_name' => 'Contact A', 'is_primary' => true]);

        $created = $this->postJson("/api/suppliers/{$supplier->id}/contacts", [
            'full_name' => 'Contact B',
            'is_primary' => true,
        ], $headers)->json('data.id');

        $this->assertDatabaseHas('supplier_contacts', ['id' => $created, 'is_primary' => true]);
        $this->assertDatabaseHas('supplier_contacts', ['id' => $first->id, 'is_primary' => false]);
    }

    public function test_admin_can_list_update_and_delete_a_contact(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $contact = $supplier->contacts()->create(['full_name' => 'Contact C']);

        $this->getJson("/api/suppliers/{$supplier->id}/contacts", $headers)->assertOk();

        $this->putJson("/api/suppliers/{$supplier->id}/contacts/{$contact->id}", [
            'full_name' => 'Contact C renomme',
        ], $headers)->assertOk();

        $this->deleteJson("/api/suppliers/{$supplier->id}/contacts/{$contact->id}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('supplier_contacts', ['id' => $contact->id]);
    }
}
