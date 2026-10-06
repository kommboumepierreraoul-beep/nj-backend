<?php

namespace Tests\Feature\Supplier;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationDirection;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class SupplierCommunicationLogTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_log_an_exchange_with_a_supplier(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();

        $this->postJson("/api/suppliers/{$supplier->id}/communication-logs", [
            'channel' => CommunicationChannel::WECHAT->value,
            'direction' => CommunicationDirection::OUTGOING->value,
            'summary' => 'Demande de mise a jour du prix.',
            'occurred_at' => now()->toDateTimeString(),
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('supplier_communication_logs', [
            'supplier_id' => $supplier->id,
            'logged_by_user_id' => $admin->id,
        ]);
    }

    public function test_admin_can_list_update_and_delete_a_communication_log(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $log = $supplier->communicationLogs()->create([
            'channel' => CommunicationChannel::EMAIL->value,
            'direction' => CommunicationDirection::INCOMING->value,
            'summary' => 'Reponse du fournisseur.',
            'occurred_at' => now(),
        ]);

        $this->getJson("/api/suppliers/{$supplier->id}/communication-logs", $headers)->assertOk();

        $this->putJson("/api/suppliers/{$supplier->id}/communication-logs/{$log->id}", [
            'summary' => 'Reponse du fournisseur (corrigee).',
        ], $headers)->assertOk();

        $this->deleteJson("/api/suppliers/{$supplier->id}/communication-logs/{$log->id}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('supplier_communication_logs', ['id' => $log->id]);
    }
}
