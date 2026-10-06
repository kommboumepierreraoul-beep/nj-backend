<?php

namespace Tests\Feature\Product;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class TagTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_admin_can_create_list_update_and_delete_a_tag(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $created = $this->postJson('/api/tags', [
            'name' => 'Promo',
            'slug' => 'promo',
            'color' => '#FF8800',
        ], $headers)->assertCreated()
            ->assertJsonPath('data.color', '#FF8800')
            ->json('data.id');

        $this->getJson('/api/tags', $headers)->assertOk();

        $this->putJson("/api/tags/{$created}", ['name' => 'Promotion', 'color' => '#00AAFF'], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Promotion')
            ->assertJsonPath('data.color', '#00AAFF');

        $this->deleteJson("/api/tags/{$created}", [], $headers)->assertOk();
        $this->assertDatabaseMissing('tags', ['id' => $created]);
    }

    public function test_tag_color_defaults_to_a_neutral_value_when_not_provided(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $created = $this->postJson('/api/tags', [
            'name' => 'Sans couleur',
            'slug' => 'sans-couleur',
        ], $headers)->assertCreated()->json('data.id');

        $this->assertDatabaseHas('tags', ['id' => $created, 'color' => '#6B7280']);
    }

    public function test_tag_color_must_be_a_valid_hex_value(): void
    {
        [, $headers] = $this->actingAsAdmin();

        $this->postJson('/api/tags', [
            'name' => 'Couleur invalide',
            'slug' => 'couleur-invalide',
            'color' => 'bleu',
        ], $headers)->assertStatus(422)->assertJsonValidationErrors('color');
    }

    public function test_tag_slug_must_be_unique(): void
    {
        [, $headers] = $this->actingAsAdmin();
        Tag::query()->create(['name' => 'Nouveaute', 'slug' => 'nouveaute']);

        $this->postJson('/api/tags', [
            'name' => 'Autre nouveaute',
            'slug' => 'nouveaute',
        ], $headers)->assertStatus(422);
    }
}
