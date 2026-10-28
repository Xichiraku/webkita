<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Events\ProjectActivityCreated;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use App\Services\ProjectActivityLogger;
use App\Services\ProjectService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

class ProjectActivityTest extends TestCase
{
    use RefreshDatabase;

    private function staff(Role $role = Role::Developer): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function project(ProjectStatus $status = ProjectStatus::Approved, ?User $developer = null, bool $paid = true): Project
    {
        $project = Project::factory()->create(['plan_price_range' => '3000000']);
        $project->status = $status;
        $project->developer_id = $developer?->id;
        $project->save();

        if ($paid) {
            Payment::factory()->for($project)->paid()->create(['amount' => 3000000]);
        }

        return $project;
    }

    private function types(Project $project): array
    {
        return ProjectActivity::where('project_id', $project->id)->orderBy('id')->pluck('type')->all();
    }

    // ── Titik pemanggilan (R6.2–R6.4) ──

    public function test_proyek_dibuat_dicatat_dengan_pelaku_klien(): void
    {
        $user = User::factory()->create();

        $id = $this->actingAs($user)->postJson('/api/projects', [
            'plan_title' => 'Starter', 'project_title' => 'Toko', 'category' => 'website', 'description' => str_repeat('Deskripsi lengkap. ', 6),
            'skills' => 'Laravel', 'contact_name' => 'Budi', 'contact_phone' => '0812',
        ])->assertCreated()->json('data.id');

        $log = ProjectActivity::where('project_id', $id)->sole();
        $this->assertSame('project.created', $log->type);
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame(['plan' => 'Starter'], $log->meta);
    }

    public function test_perubahan_status_oleh_admin_dicatat_sekali_per_perubahan(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $this->actingAs($admin);

        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'approved'])->assertOk(); // sama: tidak mencatat
        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'process'])->assertStatus(409); // gagal: tidak mencatat
        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'rejected', 'reason' => 'Di luar cakupan'])->assertOk();

        $logs = ProjectActivity::where('project_id', $project->id)->orderBy('id')->get();
        $this->assertCount(2, $logs);
        $this->assertSame(['from' => 'pending', 'to' => 'approved', 'reason' => null], $logs[0]->meta);
        $this->assertSame(['from' => 'approved', 'to' => 'rejected', 'reason' => 'Di luar cakupan'], $logs[1]->meta);
        $this->assertSame([$admin->id, $admin->id], $logs->pluck('actor_id')->all());
    }

    public function test_harga_dan_penugasan_dicatat(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $dev = $this->staff();
        $other = $this->staff();
        $project = $this->project(paid: false);
        $project->update(['plan_price_range' => null]);
        $this->actingAs($admin);

        $this->patchJson("/api/projects/{$project->id}/price", ['plan_price_range' => 'Rp 3.000.000'])->assertOk();
        $this->patchJson("/api/projects/{$project->id}/developer", ['developer_id' => $dev->id])->assertOk();
        $this->patchJson("/api/projects/{$project->id}/developer", ['developer_id' => $dev->id])->assertOk(); // sama: tidak mencatat
        $this->patchJson("/api/projects/{$project->id}/developer", ['developer_id' => $other->id])->assertOk();
        $this->patchJson("/api/projects/{$project->id}/developer", ['developer_id' => null])->assertOk();

        $logs = ProjectActivity::where('project_id', $project->id)->orderBy('id')->get();
        $this->assertSame(['price.set', 'developer.assigned', 'developer.assigned', 'developer.unassigned'], $logs->pluck('type')->all());
        $this->assertSame(['amount' => 3000000, 'invoice_number' => sprintf('INV-%04d-%d', $project->id, now()->year)], $logs[0]->meta);
        $this->assertSame($dev->name, $logs[1]->meta['developer_name']);
        $this->assertNull($logs[1]->meta['previous_name']);
        $this->assertSame($other->name, $logs[2]->meta['developer_name']);
        $this->assertSame($dev->name, $logs[2]->meta['previous_name']);
        $this->assertSame($other->name, $logs[3]->meta['developer_name']);
    }

    public function test_pembayaran_lunas_dicatat_sebagai_sistem_sekali_saja(): void
    {
        config(['services.midtrans.server_key' => 'kunci']);
        $project = $this->project(paid: false);
        $payment = Payment::factory()->for($project)->create(['amount' => 3000000]);

        $notify = function (string $status) use ($payment) {
            $body = ['order_id' => $payment->order_id, 'status_code' => '200', 'gross_amount' => '3000000.00', 'transaction_status' => $status, 'payment_type' => 'qris'];
            $body['signature_key'] = hash('sha512', $body['order_id'].'200'.'3000000.00'.'kunci');
            $this->postJson('/api/payments/notification', $body)->assertOk();
        };

        $notify('pending');
        $notify('settlement');
        $notify('settlement');
        $notify('expire');

        $log = ProjectActivity::where('project_id', $project->id)->sole();
        $this->assertSame('payment.paid', $log->type);
        $this->assertNull($log->actor_id);
        $this->assertSame(['order_id' => $payment->order_id, 'amount' => 3000000, 'method' => 'qris'], $log->meta);
    }

    public function test_perpindahan_status_otomatis_dicatat_sebagai_sistem(): void
    {
        $project = $this->project();

        app(ProjectService::class)->transitionBySystem($project, ProjectStatus::Process);

        $log = ProjectActivity::where('project_id', $project->id)->sole();
        $this->assertSame('status.changed', $log->type);
        $this->assertNull($log->actor_id);
        $this->assertSame(['from' => 'approved', 'to' => 'process', 'reason' => null], $log->meta);
    }

    // ── Atomik (R6.3) ──

    public function test_log_dibatalkan_bila_transaksi_pemicu_gagal(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $this->actingAs($admin);

        try {
            DB::transaction(function () use ($project, $admin) {
                app(ProjectService::class)->updateStatus($project, ProjectStatus::Approved, null, $admin);
                throw new Exception('paksa gagal');
            });
        } catch (Exception) {
        }

        $this->assertSame(0, ProjectActivity::count());
        $this->assertSame(ProjectStatus::Pending, $project->fresh()->status);
    }

    // ── Pencatat ──

    public function test_tipe_tidak_dikenal_dan_aksi_tanpa_pengguna_ditolak(): void
    {
        $logger = app(ProjectActivityLogger::class);
        $project = $this->project();

        try {
            $logger->logSystem($project, 'bukan.tipe');
            $this->fail('Seharusnya melempar exception');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('bukan.tipe', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('logSystem()');
        $logger->log($project, 'chat.started'); // tidak ada pengguna login
    }

    public function test_pencatat_memakai_pengguna_login_dan_waktu_kustom(): void
    {
        $user = User::factory()->create();
        $project = $this->project();
        $this->actingAs($user);
        $at = now()->subDays(3)->startOfSecond();

        $log = app(ProjectActivityLogger::class)->log($project, 'chat.started', [], null, $at);

        $this->assertSame($user->id, $log->actor_id);
        $this->assertTrue($log->created_at->equalTo($at));
        $this->assertFalse($log->usesTimestamps() && ProjectActivity::UPDATED_AT !== null, 'append-only: tanpa updated_at');
    }

    public function test_hanya_pencatat_yang_menulis_tabel_log(): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php' || $file->getFilename() === 'ProjectActivityLogger.php') {
                continue;
            }
            if (preg_match('/ProjectActivity::(create|forceCreate|insert|upsert)|new ProjectActivity|project_activities.*->(insert|upsert)/', file_get_contents($file->getPathname()))) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders, 'Tulis riwayat hanya lewat ProjectActivityLogger');
    }

    // ── Realtime ──

    public function test_aktivitas_baru_disiarkan_ke_channel_proyek(): void
    {
        Event::fake([ProjectActivityCreated::class]);
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}/status", ['status' => 'approved'])->assertOk();

        Event::assertDispatched(ProjectActivityCreated::class, function ($e) use ($project, $admin) {
            $payload = $e->broadcastWith();

            return array_map(fn ($c) => $c->name, $e->broadcastOn()) === ["private-project.{$project->id}"]
                && $e->broadcastAs() === 'activity.created'
                && $payload['type'] === 'status.changed'
                && $payload['actor'] === ['id' => $admin->id, 'name' => $admin->name, 'role' => 'admin']
                && $payload['text'] === 'Status berubah: Menunggu → Disetujui.'
                && $payload['icon'] === 'repeat';
        });
    }

    // ── API baca ──

    private function seedLogs(Project $project, int $n): void
    {
        $logger = app(ProjectActivityLogger::class);
        for ($i = 1; $i <= $n; $i++) {
            $logger->logSystem($project, 'milestone.created', ['title' => "M{$i}"]);
        }
    }

    public function test_api_riwayat_paginasi_terbaru_dulu(): void
    {
        $dev = $this->staff();
        $project = $this->project(ProjectStatus::Process, $dev);
        $this->seedLogs($project, 35);
        $this->actingAs($project->user);

        $first = $this->getJson("/api/projects/{$project->id}/activities")->assertOk();
        $this->assertCount(30, $first->json('data'));
        $this->assertTrue($first->json('meta.has_more'));
        $this->assertSame('Sistem menambahkan milestone “M35”.', $first->json('data.0.text'));
        $this->assertSame('milestone.created', $first->json('data.0.type'));
        $this->assertSame(['id' => null, 'name' => 'Sistem', 'role' => 'system'], $first->json('data.0.actor'));
        $this->assertSame('flag', $first->json('data.0.icon'));
        $this->assertSame(['title' => 'M35'], $first->json('data.0.meta'));
        $this->assertNotNull($first->json('data.0.created_at'));

        $lastId = collect($first->json('data'))->last()['id'];
        $second = $this->getJson("/api/projects/{$project->id}/activities?before_id={$lastId}")->assertOk();
        $this->assertCount(5, $second->json('data'));
        $this->assertFalse($second->json('meta.has_more'));
        $this->assertLessThan($lastId, $second->json('data.0.id'));

        $this->getJson("/api/projects/{$project->id}/activities?limit=2")->assertJsonCount(2, 'data')->assertJsonPath('meta.has_more', true);
        $this->getJson("/api/projects/{$project->id}/activities?limit=1000")->assertJsonCount(35, 'data');
        $this->getJson("/api/projects/{$project->id}/activities?limit=0")->assertJsonCount(1, 'data');
    }

    public function test_api_riwayat_izin_per_peran(): void
    {
        $dev = $this->staff();
        $project = $this->project(ProjectStatus::Process, $dev);
        $this->seedLogs($project, 1);
        $url = "/api/projects/{$project->id}/activities";

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($project->user)->getJson($url)->assertOk();
        $this->actingAs($dev)->getJson($url)->assertOk();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $this->actingAs($this->staff())->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->getJson($url)->assertForbidden();
        $this->actingAs($project->user)->getJson('/api/projects/99999/activities')->assertNotFound();
    }

    public function test_api_riwayat_terkunci_sebelum_pembayaran(): void
    {
        $dev = $this->staff();
        $project = $this->project(ProjectStatus::Approved, $dev, paid: false);

        $this->actingAs($project->user)->getJson("/api/projects/{$project->id}/activities")
            ->assertStatus(409)->assertJsonPath('message', 'Workspace aktif setelah pembayaran terkonfirmasi.');
        $this->actingAs($dev)->getJson("/api/projects/{$project->id}/activities")->assertStatus(409);
    }

    public function test_nama_pelaku_yang_dihapus_tetap_tampil(): void
    {
        $dev = $this->staff();
        $project = $this->project(ProjectStatus::Process, $dev);
        $actor = User::factory()->create(['first_name' => 'Joko', 'last_name' => 'Widodo']);
        app(ProjectActivityLogger::class)->log($project, 'chat.started', [], $actor);
        $actor->delete();

        $this->actingAs($project->user)->getJson("/api/projects/{$project->id}/activities")
            ->assertOk()->assertJsonPath('data.0.actor.name', 'Joko Widodo');
    }

    public function test_workspace_menampilkan_riwayat_terlama_di_atas(): void
    {
        $dev = $this->staff();
        $project = $this->project(ProjectStatus::Process, $dev);
        $this->seedLogs($project, 3);

        $this->actingAs($project->user)->get("/orderan/{$project->id}")
            ->assertOk()->assertSee('Riwayat Aktivitas')
            ->assertViewHas('activities', fn ($a) => count($a) === 3 && $a[0]['id'] < $a[2]['id'] && $a[0]['text'] === 'Sistem menambahkan milestone “M1”.')
            ->assertViewHas('activitiesHasMore', false);
    }

    // ── Backfill (§10) ──

    public function test_backfill_mengisi_riwayat_dan_idempoten(): void
    {
        $dev = $this->staff();
        $project = Project::factory()->create(['plan_title' => 'Starter', 'plan_price_range' => '750000', 'invoice_number' => 'INV-0001-2026']);
        $project->forceFill([
            'status' => ProjectStatus::Process, 'developer_id' => $dev->id, 'assigned_at' => now()->subDays(8),
            'invoice_issued_at' => now()->subDays(10), 'created_at' => now()->subDays(12), 'updated_at' => now()->subDays(5),
        ])->save();
        Payment::factory()->for($project)->create(['amount' => 750000, 'status' => PaymentStatus::Paid, 'paid_at' => now()->subDays(9), 'payment_type' => 'qris']);
        $pending = Project::factory()->create(); // masih pending
        $withLog = Project::factory()->create();
        app(ProjectActivityLogger::class)->logSystem($withLog, 'chat.started');

        $this->artisan('activities:backfill')->expectsOutputToContain('diisi untuk 2 proyek')->assertSuccessful();

        $this->assertSame(['project.created', 'price.set', 'payment.paid', 'developer.assigned', 'status.changed'], $this->types($project));
        $this->assertSame(['project.created'], $this->types($pending));
        $this->assertSame(['chat.started'], $this->types($withLog), 'proyek yang sudah punya log tidak disentuh');

        $logs = ProjectActivity::where('project_id', $project->id)->orderBy('id')->get();
        $this->assertSame($project->user_id, $logs[0]->actor_id);
        $this->assertNull($logs[1]->actor_id);
        $this->assertSame(750000, $logs[1]->meta['amount']);
        $this->assertSame(['from' => 'pending', 'to' => 'process', 'reason' => null, 'backfilled' => true], $logs[4]->meta);
        $times = $logs->map(fn ($l) => $l->created_at->getTimestamp())->all();
        $sorted = $times;
        sort($sorted);
        $this->assertSame($sorted, $times, 'urutan id = urutan waktu');

        $before = ProjectActivity::count();
        $this->artisan('activities:backfill')->expectsOutputToContain('diisi untuk 0 proyek')->assertSuccessful();
        $this->assertSame($before, ProjectActivity::count());
    }
}
