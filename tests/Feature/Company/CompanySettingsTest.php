<?php

namespace Tests\Feature\Company;

use App\Models\CompanySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Ecran "Parametres -> Entreprise -> Identite de l'entreprise"
 * (App\Http\Controllers\Company\CompanySettingsController).
 */
class CompanySettingsTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_the_singleton_row_is_seeded_and_readable(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->getJson('/api/company-settings', $headers)
            ->assertOk()
            ->assertJsonPath('data.legal_name', 'NJ Global Trade Co., Ltd')
            ->assertJsonPath('data.default_proforma_validity_days', 7)
            ->assertJsonStructure(['data' => ['id', 'legal_name', 'address_line', 'whatsapp', 'logo_path', 'logo_url']]);
    }

    public function test_admin_can_update_the_company_settings(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->putJson('/api/company-settings', [
            'legal_name' => 'NJ Global Trade SARL',
            'email' => 'hello@njglobaltrade.com',
            'default_proforma_validity_days' => 15,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.legal_name', 'NJ Global Trade SARL')
            ->assertJsonPath('data.default_proforma_validity_days', 15);

        $this->assertDatabaseHas('company_settings', [
            'id' => 1,
            'legal_name' => 'NJ Global Trade SARL',
            'default_proforma_validity_days' => 15,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'company_settings.updated',
            'entity_type' => 'CompanySettings',
        ]);
    }

    public function test_admin_can_upload_and_remove_the_logo(): void
    {
        Storage::fake('public');
        [, $headers] = $this->actingAsAdmin();

        $response = $this->putJson('/api/company-settings', [
            'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
        ], $headers)->assertOk();

        $path = $response->json('data.logo_path');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->deleteJson('/api/company-settings/logo', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.logo_path', null);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_validity_days_must_be_a_positive_integer(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->putJson('/api/company-settings', ['default_proforma_validity_days' => 0], $headers)
            ->assertStatus(422);
    }

    public function test_view_permission_is_required(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('company_settings.view');

        $this->getJson('/api/company-settings', $headers)->assertForbidden();
    }

    public function test_manage_permission_is_required_to_update(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $this->revokePermissionFromAdmin('company_settings.manage');

        $this->putJson('/api/company-settings', ['legal_name' => 'Nope'], $headers)->assertForbidden();
        $this->assertDatabaseMissing('company_settings', ['legal_name' => 'Nope']);
        $this->assertSame('NJ Global Trade Co., Ltd', CompanySettings::current()->legal_name);
    }
}
