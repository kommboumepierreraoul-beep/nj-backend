<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Export CSV/PDF du journal d'activite (App\Http\Controllers\AuditLogController::export()).
 * Point de cadrage spec_pages_audit.md §3.3, tranche : l'API expose desormais l'export,
 * en lecture seule (le journal reste immuable).
 */
class AuditLogExportTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_view_permission_is_required_to_export(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('audit_logs.view');

        $this->getJson('/api/audit-logs/export?format=csv', $headers)->assertForbidden();
    }

    public function test_format_is_required_and_constrained(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->getJson('/api/audit-logs/export', $headers)->assertStatus(422);
        $this->getJson('/api/audit-logs/export?format=xml', $headers)->assertStatus(422);
    }

    public function test_admin_can_export_the_journal_as_csv_with_filters_applied(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        AuditLog::record('user.updated', $userA, $admin);
        AuditLog::record('user.deleted', $userB, $admin);

        $response = $this->get('/api/audit-logs/export?format=csv&action=user.updated', $headers);

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('user.updated', $body);
        $this->assertStringNotContainsString('user.deleted', $body);
        // En-tete CSV present.
        $this->assertStringContainsString("Type d'entite", $body);
    }

    public function test_admin_can_export_the_journal_as_pdf(): void
    {
        // Meme situation que FlowAnalyticsTest (qui ne teste que le CSV) : le wrapper
        // barryvdh/laravel-dompdf n'est pas toujours present dans l'environnement d'agent.
        if (! class_exists(Pdf::class)) {
            $this->markTestSkipped('barryvdh/laravel-dompdf absent de cet environnement.');
        }

        [$admin, $headers] = $this->actingAsAdmin();
        AuditLog::record('user.updated', User::factory()->create(), $admin);

        $response = $this->get('/api/audit-logs/export?format=pdf', $headers);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_the_export_is_immutable_no_write_route_is_added(): void
    {
        [, $headers] = $this->actingAsAdmin();

        // Aucune methode d'ecriture n'est acceptee sur le journal.
        $this->postJson('/api/audit-logs/export', ['format' => 'csv'], $headers)->assertStatus(405);
        $this->deleteJson('/api/audit-logs/1', [], $headers)->assertStatus(405);
    }
}
