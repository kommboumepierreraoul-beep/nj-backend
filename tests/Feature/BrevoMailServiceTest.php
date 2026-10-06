<?php

namespace Tests\Feature;

use App\Services\BrevoMailService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BrevoMailServiceTest extends TestCase
{
    public function test_brevo_mail_service_sends_transactional_email_payload(): void
    {
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'brevo-message-id'], 201),
        ]);

        config([
            'services.brevo.api_key' => 'test-api-key',
            'services.brevo.sender_email' => 'no-reply@njglobaltrade.test',
            'services.brevo.sender_name' => 'NJ Global Trade',
        ]);

        app(BrevoMailService::class)->send(
            to: [['email' => 'user@njglobaltrade.test', 'name' => 'User Test']],
            subject: 'Test Brevo',
            htmlContent: '<p>Bonjour</p>',
            textContent: 'Bonjour',
        );

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'test-api-key')
                && $request['sender']['email'] === 'no-reply@njglobaltrade.test'
                && $request['to'][0]['email'] === 'user@njglobaltrade.test'
                && $request['subject'] === 'Test Brevo';
        });
    }
}
