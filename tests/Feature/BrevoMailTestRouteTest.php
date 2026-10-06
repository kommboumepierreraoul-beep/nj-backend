<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrevoMailTestRouteTest extends TestCase
{
    public function test_brevo_test_route_requires_token(): void
    {
        config(['services.brevo.test_token' => 'secret-test-token']);

        $this->getJson('/dev/test-brevo-mail?to=user@njglobaltrade.test')
            ->assertForbidden();
    }

    public function test_brevo_test_route_sends_email(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'brevo-message-id'], 201),
        ]);

        config([
            'services.brevo.api_key' => 'test-api-key',
            'services.brevo.sender_email' => 'no-reply@njglobaltrade.test',
            'services.brevo.sender_name' => 'NJ Global Trade',
            'services.brevo.test_token' => 'secret-test-token',
        ]);

        $this->getJson('/dev/test-brevo-mail?to=user@njglobaltrade.test&name=User%20Test&token=secret-test-token')
            ->assertOk()
            ->assertJsonPath('to', 'user@njglobaltrade.test');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.brevo.com/v3/smtp/email'
            && $request['subject'] === 'Test email Brevo - NJ Global Trade'
            && $request['to'][0]['email'] === 'user@njglobaltrade.test');
    }
}
