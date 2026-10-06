<?php

namespace Tests\Feature\Supplier;

use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\AuthenticatesAdmin;
use Tests\TestCase;

class SupplierEvaluationTest extends TestCase
{
    use AuthenticatesAdmin, RefreshDatabase;

    public function test_creating_an_evaluation_updates_the_supplier_reliability_score(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();

        $this->postJson("/api/suppliers/{$supplier->id}/evaluations", [
            'quality_score' => 5,
            'communication_score' => 5,
            'delay_respect_score' => 5,
            'price_competitiveness_score' => 5,
            'evaluated_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('supplier_evaluations', [
            'supplier_id' => $supplier->id,
            'evaluated_by_user_id' => $admin->id,
            'overall_score' => 5,
        ]);
        $this->assertEquals(5, $supplier->fresh()->reliability_score);
    }

    public function test_the_reliability_score_is_the_average_of_all_evaluations(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();

        $this->postJson("/api/suppliers/{$supplier->id}/evaluations", [
            'quality_score' => 5, 'communication_score' => 5, 'delay_respect_score' => 5, 'price_competitiveness_score' => 5,
            'evaluated_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->postJson("/api/suppliers/{$supplier->id}/evaluations", [
            'quality_score' => 1, 'communication_score' => 1, 'delay_respect_score' => 1, 'price_competitiveness_score' => 1,
            'evaluated_at' => now()->toDateString(),
        ], $headers)->assertCreated();

        $this->assertEquals(3, $supplier->fresh()->reliability_score);
    }

    public function test_deleting_the_only_evaluation_resets_the_reliability_score_to_null(): void
    {
        [$admin, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $evaluation = $supplier->evaluations()->create([
            'quality_score' => 4, 'communication_score' => 4, 'delay_respect_score' => 4, 'price_competitiveness_score' => 4,
            'evaluated_by_user_id' => $admin->id,
            'evaluated_at' => now()->toDateString(),
        ]);

        $this->deleteJson("/api/suppliers/{$supplier->id}/evaluations/{$evaluation->id}", [], $headers)->assertOk();

        $this->assertNull($supplier->fresh()->reliability_score);
    }

    public function test_a_score_must_be_between_one_and_five(): void
    {
        [, $headers] = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();

        $this->postJson("/api/suppliers/{$supplier->id}/evaluations", [
            'quality_score' => 6, 'communication_score' => 1, 'delay_respect_score' => 1, 'price_competitiveness_score' => 1,
            'evaluated_at' => now()->toDateString(),
        ], $headers)->assertStatus(422)->assertJsonValidationErrors(['quality_score']);
    }
}
