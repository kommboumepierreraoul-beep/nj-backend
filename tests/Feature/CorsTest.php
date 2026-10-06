<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_configured_frontend_origin_is_granted_cors_access(): void
    {
        $response = $this->withHeaders(['Origin' => 'http://localhost:3000'])
            ->getJson('/api/auth/sessions');

        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_an_unrecognized_origin_never_gets_its_own_origin_reflected_back(): void
    {
        // Avant l'ajout de config/cors.php, Laravel retombait sur son defaut
        // "allowed_origins => ['*']" : n'importe quelle origine, y compris celle-ci,
        // recevait un Access-Control-Allow-Origin qui la reflete (ou "*") et pouvait
        // donc lire la reponse depuis un navigateur. Desormais, l'en-tete renvoye est
        // toujours l'origine front autorisee (statique), jamais celle de l'appelant :
        // un navigateur compare cet en-tete a l'origine REELLE de la page appelante,
        // donc un site malveillant ne peut jamais obtenir une correspondance et se
        // voit bloquer la lecture de la reponse cote navigateur.
        $response = $this->withHeaders(['Origin' => 'https://site-malveillant.example'])
            ->getJson('/api/auth/sessions');

        $this->assertNotSame(
            'https://site-malveillant.example',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }
}
