<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Services\BrevoMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class BrevoMailTestController extends Controller
{
    public function __invoke(Request $request, BrevoMailService $brevo): JsonResponse
    {
        $configuredToken = config('services.brevo.test_token');

        if (! $configuredToken || ! hash_equals($configuredToken, (string) $request->query('token'))) {
            return response()->json(['message' => 'Token de test invalide.'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'to' => ['required', 'email'],
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        try {
            $brevo->send(
                to: [[
                    'email' => $validated['to'],
                    'name' => $validated['name'] ?? 'Destinataire test',
                ]],
                subject: 'Test email Brevo - NJ Global Trade',
                htmlContent: view('emails.dev.brevo-test', [
                    'name' => $validated['name'] ?? 'Destinataire test',
                ])->render(),
                textContent: 'Email de test Brevo envoye depuis NJ Global Trade.',
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Echec envoi Brevo.',
                'error' => $exception->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'message' => 'Email de test envoye via Brevo.',
            'to' => $validated['to'],
        ]);
    }
}
