<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationPriority;
use App\Enums\SalesOrderStatus;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Notification;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

/**
 * Centre de notifications interne (Doc/notifications_modele_donnees.md, §6/§7),
 * App\Http\Controllers\NotificationController. Ressource personnelle : aucune permission de
 * role a verifier (decision §2.5), seulement la restriction au proprietaire.
 */
class NotificationTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_guest_cannot_view_notifications(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_user_only_sees_their_own_notifications(): void
    {
        [$me, $headers] = $this->actingAsAdmin();
        [$someoneElse] = $this->actingAsAdmin();

        $this->createNotification($me, ['title' => 'Pour moi']);
        $this->createNotification($someoneElse, ['title' => 'Pour un autre']);

        $response = $this->getJson('/api/notifications', $headers)->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame('Pour moi', $response->json('data.0.title'));
    }

    public function test_filters_by_category_priority_and_read_status(): void
    {
        [$me, $headers] = $this->actingAsAdmin();

        $unreadPaiement = $this->createNotification($me, [
            'category' => NotificationCategory::PAIEMENT->value,
            'priority' => NotificationPriority::IMPORTANT->value,
        ]);
        $this->createNotification($me, [
            'category' => NotificationCategory::ACHAT->value,
            'priority' => NotificationPriority::INFO->value,
            'read_at' => now(),
        ]);

        $response = $this->getJson('/api/notifications?category=PAIEMENT&read=0', $headers)->assertOk();

        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame($unreadPaiement->id, $response->json('data.0.id'));
    }

    public function test_unread_count_only_counts_the_authenticated_users_unread_notifications(): void
    {
        [$me, $headers] = $this->actingAsAdmin();
        [$someoneElse] = $this->actingAsAdmin();

        $this->createNotification($me);
        $this->createNotification($me, ['read_at' => now()]);
        $this->createNotification($someoneElse);

        $this->getJson('/api/notifications/unread-count', $headers)
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1);
    }

    public function test_mark_read_updates_read_at(): void
    {
        [$me, $headers] = $this->actingAsAdmin();
        $notification = $this->createNotification($me);

        $this->patchJson("/api/notifications/{$notification->id}/read", [], $headers)
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_read_is_forbidden_for_another_users_notification(): void
    {
        [, $headers] = $this->actingAsAdmin();
        [$someoneElse] = $this->actingAsAdmin();
        $notification = $this->createNotification($someoneElse);

        $this->patchJson("/api/notifications/{$notification->id}/read", [], $headers)->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read(): void
    {
        [$me, $headers] = $this->actingAsAdmin();
        $this->createNotification($me);
        $this->createNotification($me);
        $this->createNotification($me, ['read_at' => now()]);

        $response = $this->patchJson('/api/notifications/read-all', [], $headers)->assertOk();

        $this->assertSame(2, $response->json('data.updated_count'));
        $this->assertSame(0, Notification::query()->where('notifiable_user_id', $me->id)->whereNull('read_at')->count());
    }

    // Evenement #2 du module (Doc/notifications_modele_donnees.md, §5) : verifie le cablage
    // reel dans SalesOrderController::updateStatus(), pas seulement l'API du centre de
    // notifications. Http::fake() empeche tout appel reseau reel vers Brevo (canal email,
    // active par defaut pour la categorie COMMANDE).
    public function test_sales_order_status_change_creates_an_in_app_notification_for_the_creator(): void
    {
        Http::fake();

        [$creator, $headers] = $this->actingAsAdmin();
        $client = Client::factory()->create();
        $currency = Currency::factory()->create();
        $salesOrder = SalesOrder::factory()->create([
            'client_id' => $client->id,
            'currency_id' => $currency->id,
            'status' => SalesOrderStatus::BROUILLON->value,
            'created_by_user_id' => $creator->id,
        ]);

        $this->putJson("/api/sales-orders/{$salesOrder->id}/status", ['status' => SalesOrderStatus::CONFIRMEE->value], $headers)
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'notifiable_user_id' => $creator->id,
            'type' => 'sales_order.status_changed',
            'related_entity_type' => 'SalesOrder',
            'related_entity_id' => $salesOrder->id,
        ]);
    }

    private function createNotification(\App\Models\User $user, array $attributes = []): Notification
    {
        return Notification::query()->create(array_merge([
            'notifiable_user_id' => $user->id,
            'type' => 'test.event',
            'category' => NotificationCategory::COMMANDE->value,
            'priority' => NotificationPriority::INFO->value,
            'title' => 'Titre de test',
            'body' => 'Corps de test',
        ], $attributes));
    }
}
