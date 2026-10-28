<?php

namespace Tests\Feature\Api;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Exceptions\ApiException;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'plan_title' => 'Starter', 'price_range' => 'Rp 750.000',
            'project_title' => 'Toko Online', 'category' => 'website',
            'description' => str_repeat('Deskripsi lengkap proyek. ', 5),
            'skills' => 'Laravel', 'contact_name' => 'Budi', 'contact_phone' => '0812',
        ], $override);
    }

    private function developer(): User
    {
        $dev = User::factory()->create();
        $dev->role = Role::Developer;
        $dev->save();

        return $dev;
    }

    public function test_buat_project(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->postJson('/api/projects', $this->payload(['status' => 'done', 'user_id' => 999]))
            ->assertCreated()->assertJsonPath('message', 'Project berhasil dibuat');

        $project = Project::findOrFail($res->json('data.id'));
        $this->assertSame($user->id, $project->user_id);
        $this->assertSame(ProjectStatus::Pending, $project->status);
    }

    public function test_validasi_buat_project(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/projects', $this->payload(['description' => 'pendek']))->assertStatus(422)->assertJsonValidationErrors('description');
        $this->postJson('/api/projects', $this->payload(['category' => 'lain']))->assertStatus(422)->assertJsonValidationErrors('category');
        $this->postJson('/api/projects', [])->assertStatus(422);
        $this->postJson('/api/projects', $this->payload())->assertCreated();
    }

    public function test_butuh_login(): void
    {
        $this->getJson('/api/projects/my')->assertUnauthorized();
        $this->postJson('/api/projects', $this->payload())->assertUnauthorized();
    }

    public function test_daftar_milik_sendiri_dan_akses_detail(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $mine = Project::factory()->for($owner)->create();
        Project::factory()->for($other)->create();

        $this->actingAs($owner)->getJson('/api/projects/my')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($owner)->getJson("/api/projects/{$mine->id}")->assertOk();
        $this->actingAs($other)->getJson("/api/projects/{$mine->id}")->assertForbidden();
        $this->actingAs($this->developer())->getJson("/api/projects/{$mine->id}")->assertOk();
        $this->actingAs($owner)->getJson('/api/projects/99999')->assertNotFound();
    }

    public function test_semua_project_dan_per_user_hanya_admin(): void
    {
        $owner = User::factory()->create();
        Project::factory()->for($owner)->count(2)->create();
        Project::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($owner)->getJson('/api/projects')->assertForbidden();
        $this->actingAs($this->developer())->getJson('/api/projects')->assertForbidden();
        $this->actingAs($owner)->getJson("/api/projects/user/{$owner->id}")->assertForbidden();

        $this->actingAs($admin)->getJson('/api/projects')->assertOk()->assertJsonCount(3, 'data');
        $this->actingAs($admin)->getJson("/api/projects/user/{$owner->id}")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_alur_status_manual_admin(): void
    {
        $project = Project::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($project->user)->patchJson("/api/projects/{$project->id}/status", ['status' => 'approved'])->assertForbidden();
        $this->actingAs($admin);

        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'bukan'])->assertStatus(422);
        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'done'])->assertStatus(409); // pending -> done: tidak valid
        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'approved'])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'approved'])->assertOk(); // tidak ada perubahan

        // process dan done tidak bisa dipilih admin; sistem yang memindahkan
        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'process'])->assertStatus(409)
            ->assertJsonPath('message', 'Status ini diatur otomatis oleh sistem.');
        $this->assertSame(ProjectStatus::Approved, $project->fresh()->status);
    }

    public function test_status_process_dan_done_hanya_oleh_sistem(): void
    {
        $project = Project::factory()->create();
        $project->status = ProjectStatus::Approved;
        $project->save();
        $service = app(ProjectService::class);

        $this->assertTrue($service->transitionBySystem($project, ProjectStatus::Process));
        $this->assertSame(ProjectStatus::Process, $project->fresh()->status);

        $this->assertTrue($service->transitionBySystem($project->fresh(), ProjectStatus::Done));
        $done = $project->fresh();
        $this->assertSame(ProjectStatus::Done, $done->status);
        $this->assertNotNull($done->completed_at);
        $this->assertSame(100, $done->progress_percent);

        $this->expectException(ApiException::class);
        $service->transitionBySystem($done, ProjectStatus::Process);
    }

    public function test_perpindahan_sistem_kalah_balapan_mengembalikan_false(): void
    {
        $project = Project::factory()->create();
        $project->status = ProjectStatus::Approved;
        $project->save();
        $stale = Project::find($project->id);

        $service = app(ProjectService::class);
        $this->assertTrue($service->transitionBySystem($project, ProjectStatus::Process));
        $this->assertFalse($service->transitionBySystem($stale, ProjectStatus::Process), 'status asal sudah berubah');
    }

    public function test_tolak_dengan_alasan_bersifat_final(): void
    {
        $project = Project::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'rejected', 'reason' => str_repeat('x', 501)])->assertStatus(422);
        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'rejected', 'reason' => '  Di luar cakupan  '])
            ->assertOk()->assertJsonPath('data.reject_reason', 'Di luar cakupan');
        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'approved'])
            ->assertStatus(409)->assertJsonPath('message', 'Orderan yang ditolak tidak dapat diubah lagi');
    }

    public function test_tetapkan_harga_menerbitkan_invoice_sekali_saja(): void
    {
        $project = Project::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/projects/{$project->id}/price", ['plan_price_range' => 'abc'])->assertStatus(400)->assertJsonPath('message', 'Harga tidak valid');
        $this->patchJson("/api/projects/{$project->id}/price", ['plan_price_range' => 5000])->assertStatus(400)->assertJsonPath('message', 'Harga di luar batas yang diizinkan');
        $this->patchJson("/api/projects/{$project->id}/price", ['plan_price_range' => 20_000_000_000])->assertStatus(400);

        $this->patchJson("/api/projects/{$project->id}/price", ['plan_price_range' => 'Rp 3.000.000'])
            ->assertOk()
            ->assertJsonPath('data.plan_price_range', '3000000')
            ->assertJsonPath('data.invoice_number', sprintf('INV-%04d-%d', $project->id, now()->year));

        $this->assertNotNull($project->fresh()->invoice_issued_at);
        $this->patchJson("/api/projects/{$project->id}/price", ['plan_price_range' => 4000000])->assertStatus(403);
        $this->assertSame('3000000', $project->fresh()->plan_price_range);
    }

    public function test_harga_tidak_bisa_ditetapkan_untuk_orderan_ditolak(): void
    {
        $project = Project::factory()->create();
        $project->status = ProjectStatus::Rejected;
        $project->save();
        $this->actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/projects/{$project->id}/price", ['plan_price_range' => 3000000])->assertStatus(409);
    }

    public function test_hapus_adalah_soft_delete_hanya_admin(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($project->user)->deleteJson("/api/projects/{$project->id}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->deleteJson("/api/projects/{$project->id}")->assertOk();

        $this->assertSoftDeleted($project);
        $this->getJson("/api/projects/{$project->id}")->assertNotFound();
        $this->actingAs($project->user)->getJson('/api/projects/my')->assertJsonCount(0, 'data');
    }
}
