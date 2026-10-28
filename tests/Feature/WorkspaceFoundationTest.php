<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Events\ProjectUpdated;
use App\Mail\DeveloperAssignedMail;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Fondasi Project Workspace (tahap T0): kolom baru, penugasan developer, akses Workspace,
 * halaman terkunci sebelum pembayaran, dan "Proyek Saya".
 */
class WorkspaceFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function staff(Role $role = Role::Developer, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function approved(array $attrs = [], ?User $developer = null, bool $paid = false): Project
    {
        $project = Project::factory()->create($attrs + ['plan_price_range' => '3000000']);
        $project->status = ProjectStatus::Approved;
        if ($developer) {
            $project->developer_id = $developer->id;
            $project->assigned_at = now();
        }
        $project->save();

        if ($paid) {
            Payment::factory()->for($project)->paid()->create(['amount' => 3000000]);
        }

        return $project;
    }

    // ── Kolom & model ──

    public function test_proyek_baru_memakai_nilai_awal_workspace(): void
    {
        $project = app(ProjectService::class)->create(User::factory()->create(), [
            'project_title' => 'Toko', 'category' => 'website', 'description' => str_repeat('x', 100),
            'skills' => 'Laravel', 'contact_name' => 'Budi', 'contact_phone' => '0812',
        ]);

        $this->assertNull($project->developer_id);
        $this->assertNull($project->assigned_at);
        $this->assertSame(0, $project->progress_percent);
        $this->assertSame(0, $project->revisions_used);
        $this->assertSame(3, $project->revision_limit);
        $this->assertNull($project->completed_at);
    }

    public function test_pembayaran_terkonfirmasi_hanya_bila_ada_pembayaran_paid(): void
    {
        $project = $this->approved();
        $this->assertFalse($project->isPaymentConfirmed());

        Payment::factory()->for($project)->create(['status' => PaymentStatus::Pending, 'amount' => 3000000]);
        Payment::factory()->for($project)->create(['status' => PaymentStatus::Failed, 'amount' => 3000000]);
        Payment::factory()->for($project)->create(['status' => PaymentStatus::Challenge, 'amount' => 3000000]);
        $this->assertFalse($project->isPaymentConfirmed());

        Payment::factory()->for($project)->paid()->create(['amount' => 3000000]);
        $this->assertTrue($project->isPaymentConfirmed());
    }

    // ── Penugasan developer ──

    public function test_admin_menugaskan_developer_dan_admin_diri_sendiri(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $dev = $this->staff();
        $project = $this->approved();
        $this->actingAs($admin);

        $this->patchJson("/api/projects/{$project->id}/developer", ['developer_id' => $dev->id])
            ->assertOk()->assertJsonPath('data.developer_id', $dev->id)->assertJsonPath('message', 'Developer ditugaskan');
        $this->assertSame($dev->id, $project->fresh()->developer_id);
        $this->assertNotNull($project->fresh()->assigned_at);
        Mail::assertQueued(DeveloperAssignedMail::class, fn ($m) => $m->hasTo($dev->email));

        // admin = developer: boleh menugaskan diri sendiri
        $this->patchJson("/api/projects/{$project->id}/developer", ['developer_id' => $admin->id])->assertOk();
        $this->assertSame($admin->id, $project->fresh()->developer_id);

        // melepas
        $this->patchJson("/api/projects/{$project->id}/developer", ['developer_id' => null])->assertOk()->assertJsonPath('message', 'Developer dilepas');
        $this->assertNull($project->fresh()->developer_id);
        $this->assertNull($project->fresh()->assigned_at);
    }

    public function test_hanya_admin_yang_boleh_menugaskan(): void
    {
        $dev = $this->staff();
        $project = $this->approved();

        $this->patchJson("/api/projects/{$project->id}/developer", ['developer_id' => $dev->id])->assertUnauthorized();
        $this->actingAs($project->user)->patchJson("/api/projects/{$project->id}/developer", ['developer_id' => $dev->id])->assertForbidden();
        $this->actingAs($dev)->patchJson("/api/projects/{$project->id}/developer", ['developer_id' => $dev->id])->assertForbidden();
        $this->assertNull($project->fresh()->developer_id);
    }

    public function test_validasi_penugasan(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = $this->approved();
        $url = "/api/projects/{$project->id}/developer";

        $this->patchJson($url, ['developer_id' => User::factory()->create()->id])->assertStatus(422)->assertJsonPath('message', 'Developer tidak valid');
        $this->patchJson($url, ['developer_id' => $this->staff(Role::Developer, ['is_aktif' => 'N'])->id])->assertStatus(422);
        $this->patchJson($url, ['developer_id' => 99999])->assertStatus(422);
        $this->patchJson($url, ['developer_id' => 'abc'])->assertStatus(422);
        $deleted = $this->staff();
        $deleted->delete();
        $this->patchJson($url, ['developer_id' => $deleted->id])->assertStatus(422);
        $this->patchJson($url, [])->assertStatus(422); // developer_id wajib hadir (boleh null)
    }

    public function test_penugasan_hanya_pada_proyek_disetujui_atau_berjalan(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $dev = $this->staff();

        foreach ([ProjectStatus::Pending, ProjectStatus::Done, ProjectStatus::Rejected] as $status) {
            $project = Project::factory()->create();
            $project->status = $status;
            $project->save();

            $this->patchJson("/api/projects/{$project->id}/developer", ['developer_id' => $dev->id])
                ->assertStatus(409)->assertJsonPath('message', 'Developer hanya dapat ditugaskan pada proyek yang sudah disetujui.');
        }

        $process = Project::factory()->create();
        $process->status = ProjectStatus::Process;
        $process->save();
        $this->patchJson("/api/projects/{$process->id}/developer", ['developer_id' => $dev->id])->assertOk();
    }

    public function test_penugasan_menyiarkan_event_ke_klien_developer_dan_admin(): void
    {
        Mail::fake();
        Event::fake([ProjectUpdated::class]);
        $this->actingAs(User::factory()->admin()->create());
        $dev = $this->staff();
        $project = $this->approved();

        $this->patchJson("/api/projects/{$project->id}/developer", ['developer_id' => $dev->id])->assertOk();
        $this->patchJson("/api/projects/{$project->id}/developer", ['developer_id' => $dev->id])->assertOk(); // sama: tanpa event/email baru

        Event::assertDispatchedTimes(ProjectUpdated::class, 1);
        Event::assertDispatched(ProjectUpdated::class, function ($e) use ($project, $dev) {
            $names = array_map(fn ($c) => $c->name, $e->broadcastOn());

            return $names === ["private-App.Models.User.{$project->user_id}", "private-App.Models.User.{$dev->id}", 'private-admin.orders']
                && $e->broadcastWith()['developer_id'] === $dev->id
                && $e->broadcastWith()['progress_percent'] === 0;
        });
        Mail::assertQueuedCount(1);
    }

    // ── Akses Workspace ──

    public function test_workspace_aktif_untuk_klien_dan_developer_setelah_dibayar(): void
    {
        $dev = $this->staff();
        $project = $this->approved(['project_title' => 'Toko Batik'], $dev, paid: true);

        $this->actingAs($project->user)->get("/orderan/{$project->id}")
            ->assertOk()->assertSee('PROJECT WORKSPACE', false)->assertSee('Toko Batik')->assertSee('Ringkasan')->assertSee('Chat')
            ->assertSee($dev->name)->assertDontSee('Workspace belum aktif');

        $this->actingAs($dev)->get("/developer/proyek/{$project->id}")
            ->assertOk()->assertSee('PROJECT WORKSPACE', false)->assertSee($project->contact_name);
    }

    public function test_workspace_terkunci_sebelum_pembayaran(): void
    {
        $dev = $this->staff();
        $project = $this->approved([], $dev, paid: false);

        $this->actingAs($project->user)->get("/orderan/{$project->id}")
            ->assertOk()->assertSee('Workspace belum aktif')->assertSee('Workspace aktif setelah pembayaran terkonfirmasi.')->assertSee('Lihat Invoice dan Bayar')
            ->assertDontSee('PROJECT WORKSPACE', false);

        $this->actingAs($dev)->get("/developer/proyek/{$project->id}")
            ->assertOk()->assertSee('Workspace belum aktif')->assertSee('Menunggu pembayaran klien.');
    }

    public function test_admin_tanpa_penugasan_tidak_masuk_workspace(): void
    {
        $dev = $this->staff();
        $project = $this->approved([], $dev, paid: true);
        $admin = User::factory()->admin()->create();
        $otherDev = $this->staff();
        $otherClient = User::factory()->create();

        $this->actingAs($admin)->get("/developer/proyek/{$project->id}")->assertForbidden();
        $this->actingAs($otherDev)->get("/developer/proyek/{$project->id}")->assertForbidden();
        $this->actingAs($otherClient)->get("/orderan/{$project->id}")->assertForbidden();
        $this->actingAs($admin)->get("/orderan/{$project->id}")->assertForbidden();
        $this->actingAs($dev)->get("/orderan/{$project->id}")->assertForbidden(); // developer bukan pemilik
        $this->actingAs($project->user)->get("/developer/proyek/{$project->id}")->assertForbidden(); // klien: role middleware
    }

    public function test_admin_yang_ditugaskan_masuk_workspace_sebagai_developer(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->approved([], $admin, paid: true);

        $this->actingAs($admin)->get("/developer/proyek/{$project->id}")->assertOk()->assertSee('PROJECT WORKSPACE', false);
    }

    public function test_workspace_butuh_login_dan_proyek_ada(): void
    {
        $project = $this->approved();

        $this->get("/orderan/{$project->id}")->assertRedirect('/login');
        $this->get("/developer/proyek/{$project->id}")->assertRedirect('/login');
        $this->actingAs($project->user)->get('/orderan/99999')->assertNotFound();
        $this->actingAs($project->user)->get('/orderan/baru')->assertOk()->assertSee('Informasi Detail Proyek'); // tidak bentrok dengan {project}
    }

    // ── Proyek Saya ──

    public function test_proyek_saya_hanya_menampilkan_yang_ditugaskan(): void
    {
        $dev = $this->staff();
        $mine = $this->approved(['project_title' => 'Milik Saya'], $dev, paid: true);
        $unpaid = $this->approved(['project_title' => 'Belum Dibayar'], $dev);
        $this->approved(['project_title' => 'Milik Orang Lain'], $this->staff(), paid: true);
        $this->approved(['project_title' => 'Tanpa Developer']);

        $this->actingAs($dev)->get('/developer/proyek')
            ->assertOk()->assertSee('Milik Saya')->assertSee('Belum Dibayar')->assertSee('Menunggu pembayaran')
            ->assertDontSee('Milik Orang Lain')->assertDontSee('Tanpa Developer')
            ->assertSee(route('developer.projects.show', $mine), false)->assertSee(route('developer.projects.show', $unpaid), false);
    }

    public function test_proyek_saya_kosong_dan_hanya_untuk_staf(): void
    {
        $this->actingAs($this->staff())->get('/developer/proyek')->assertOk()->assertSee('Belum ada proyek yang ditugaskan');
        $this->actingAs(User::factory()->admin()->create())->get('/developer/proyek')->assertOk();
        $this->actingAs(User::factory()->create())->get('/developer/proyek')->assertForbidden();
        auth()->logout();
        $this->get('/developer/proyek')->assertRedirect('/login');
    }

    public function test_navbar_menampilkan_proyek_saya_hanya_untuk_staf(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertDontSee('Proyek Saya');
        $this->actingAs($this->staff())->get('/')->assertSee('Proyek Saya');
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertSee('Proyek Saya');
    }

    public function test_daftar_orderan_klien_memuat_tautan_workspace(): void
    {
        $project = $this->approved([], null, paid: true);

        $this->actingAs($project->user)->get('/orderan')
            ->assertOk()->assertSee('Buka Workspace')
            ->assertViewHas('orders', fn ($orders) => $orders->first()['workspaceUrl'] === route('orderan.show', $project));
    }
}
