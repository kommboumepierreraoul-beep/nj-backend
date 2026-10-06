<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Preferences de canal (Doc/notifications_modele_donnees.md, §3.3/§6/§7),
 * App\Http\Controllers\NotificationPreferenceController. Un seul canal desactivable
 * (email) — SMS/WhatsApp retires du perimetre le 2026-08-27.
 */
class NotificationPreferenceTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_guest_cannot_view_preferences(): void
    {
        $this->getJson('/api/notification-preferences')->assertUnauthorized();
    }

    // Valeur par defaut de NotificationCategory::defaultChannels() (Doc/notifications_modele_donnees.md,
    // §3.3) : email active pour les 6 categories sans qu'aucune ligne notification_preferences
    // n'existe en base.
    public function test_default_preferences_are_returned_without_any_stored_row(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $response = $this->getJson('/api/notification-preferences', $headers)->assertOk();

        $this->assertSame(0, NotificationPreference::query()->count());

        $byCategory = collect($response->json('data'))->keyBy('category');
        $this->assertTrue($byCategory['COMMANDE']['email_enabled']);
        $this->assertTrue($byCategory['RELANCE']['email_enabled']);
    }

    public function test_update_preferences_persists_and_is_reflected_on_read(): void
    {
        [$user, $headers] = $this->actingAsAdmin();

        $this->putJson('/api/notification-preferences', [
            'preferences' => [
                ['category' => 'ACHAT', 'email_enabled' => false],
            ],
        ], $headers)->assertOk();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'category' => 'ACHAT',
            'email_enabled' => false,
        ]);

        $response = $this->getJson('/api/notification-preferences', $headers)->assertOk();
        $achat = collect($response->json('data'))->firstWhere('category', 'ACHAT');
        $this->assertFalse($achat['email_enabled']);
    }
}
