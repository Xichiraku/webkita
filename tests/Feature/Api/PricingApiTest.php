<?php

namespace Tests\Feature\Api;

use App\Models\PlanFeature;
use App\Models\PricingPlan;
use App\Models\User;
use Database\Seeders\PricingPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'title' => 'Custom', 'tier' => 'custom', 'min_price' => 100000, 'max_price' => 200000,
            'price_range' => 'Rp 100.000 – Rp 200.000',
            'features' => [['feature' => 'Satu'], ['feature' => 'Dua']],
            'not_included' => [['feature' => 'Hosting']],
        ], $override);
    }

    public function test_publik_hanya_menampilkan_paket_aktif_berurutan(): void
    {
        $this->seed(PricingPlanSeeder::class);
        PricingPlan::where('tier', 'business')->update(['is_active' => false]);

        $res = $this->getJson('/api/pricing/public')->assertOk();

        $this->assertSame(['starter', 'professional', 'enterprise'], collect($res->json('data'))->pluck('tier')->all());
        $this->assertSame(500000, $res->json('data.0.min_price'));
        $this->assertNull($res->json('data.2.max_price'));
        $this->assertCount(3, $res->json('data.0.features'));
        $this->assertSame('Desain responsif', $res->json('data.0.features.0.feature'));
    }

    public function test_publik_per_tier(): void
    {
        $this->seed(PricingPlanSeeder::class);

        $this->getJson('/api/pricing/public/tier/professional')->assertOk()->assertJsonPath('data.title', 'Professional');
        $this->getJson('/api/pricing/public/tier/tidakada')->assertNotFound()->assertJsonPath('status', 'error');
    }

    public function test_crud_admin_dibatasi_role(): void
    {
        $this->getJson('/api/pricing')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/pricing')->assertForbidden();
        $this->actingAs($this->admin())->getJson('/api/pricing')->assertOk();
    }

    public function test_buat_ubah_hapus_paket(): void
    {
        $this->actingAs($this->admin());

        $id = $this->postJson('/api/pricing', $this->payload())
            ->assertCreated()
            ->assertJsonCount(2, 'data.features')
            ->assertJsonPath('data.features.1.sort_order', 1)
            ->json('data.id');

        // ganti daftar fitur & ubah satu field; not_included tidak dikirim -> tetap
        $this->putJson("/api/pricing/{$id}", ['title' => 'Baru', 'features' => [['feature' => 'Tiga']]])
            ->assertOk()
            ->assertJsonPath('data.title', 'Baru')
            ->assertJsonCount(1, 'data.features')
            ->assertJsonCount(1, 'data.not_included');

        $this->putJson("/api/pricing/{$id}", ['not_included' => []])->assertOk()->assertJsonCount(0, 'data.not_included');

        $this->deleteJson("/api/pricing/{$id}")->assertOk();
        $this->assertDatabaseMissing('pricing_plans', ['id' => $id]);
        $this->assertSame(0, PlanFeature::count());
    }

    public function test_validasi_rentang_harga(): void
    {
        $this->actingAs($this->admin());

        $this->postJson('/api/pricing', $this->payload(['max_price' => 100000]))
            ->assertStatus(400)->assertJsonPath('message', 'max_price harus lebih besar dari min_price');
        $this->postJson('/api/pricing', $this->payload(['min_price' => -1]))->assertStatus(422);
        $this->postJson('/api/pricing', ['title' => 'x'])->assertStatus(422);

        // tanpa batas atas diperbolehkan
        $id = $this->postJson('/api/pricing', $this->payload(['max_price' => null]))->assertCreated()->assertJsonPath('data.max_price', null)->json('data.id');

        // update hanya min_price dibandingkan dengan max yang sudah ada
        $this->putJson("/api/pricing/{$id}", ['max_price' => 150000])->assertOk();
        $this->putJson("/api/pricing/{$id}", ['min_price' => 150000])->assertStatus(400);
        $this->putJson("/api/pricing/{$id}", ['min_price' => 120000])->assertOk();
    }

    public function test_toggle_aktif(): void
    {
        $this->seed(PricingPlanSeeder::class);
        $plan = PricingPlan::first();
        $this->actingAs($this->admin());

        $this->patchJson("/api/pricing/{$plan->id}/toggle-active")->assertOk()->assertJsonPath('data.is_active', false);
        $this->patchJson("/api/pricing/{$plan->id}/toggle-active")->assertOk()->assertJsonPath('data.is_active', true);
    }
}
