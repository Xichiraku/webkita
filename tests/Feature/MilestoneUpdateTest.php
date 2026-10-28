<?php

namespace Tests\Feature;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Events\MilestoneChanged;
use App\Events\ProjectUpdated;
use App\Events\ProjectUpdateSubmitted;
use App\Mail\ResultSubmittedMail;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectAttachment;
use App\Models\ProjectMilestone;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Services\ProjectActivityLogger;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class MilestoneUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    private Project $project;

    private ProjectMilestone $milestone;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->dev = $this->staff();
        $this->project = $this->workspace($this->dev);
        $this->milestone = $this->milestone('Desain');
    }

    private function staff(Role $role = Role::Developer): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function workspace(User $dev, ProjectStatus $status = ProjectStatus::Approved, bool $paid = true): Project
    {
        $project = Project::factory()->create(['plan_price_range' => '3000000']);
        $project->status = $status;
        $project->developer_id = $dev->id;
        $project->save();

        if ($paid) {
            Payment::factory()->for($project)->paid()->create(['amount' => 3000000]);
        }

        return $project;
    }

    private function milestone(string $title, MilestoneStatus $status = MilestoneStatus::Planned, int $percent = 0, ?Project $project = null): ProjectMilestone
    {
        $project ??= $this->project;
        $m = new ProjectMilestone(['title' => $title]);
        $m->project_id = $project->id;
        $m->position = $project->milestones()->count() + 1;
        $m->status = $status;
        $m->percent = $percent;
        $m->save();

        return $m;
    }

    private function send(array $data, ?ProjectMilestone $milestone = null): TestResponse
    {
        return $this->postJson('/api/milestones/'.($milestone ?? $this->milestone)->id.'/updates', $data);
    }

    private function progress(int $percent = 40, array $extra = []): array
    {
        return array_replace(['type' => 'progress', 'percent' => $percent, 'note' => 'Wireframe halaman utama selesai'], $extra);
    }

    private function resultPayload(array $extra = []): array
    {
        return array_replace(['type' => 'result', 'title' => 'Desain versi 1', 'note' => 'Mockup semua halaman sudah selesai', 'links' => ['https://staging.contoh.test']], $extra);
    }

    private function zip(string $name = 'desain.zip', int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'application/zip');
    }

    // ── Progres ──

    public function test_progres_pertama_memindahkan_proyek_ke_process(): void
    {
        $this->milestone('Pengembangan'); // milestone kedua (planned) -> rata-rata
        $this->actingAs($this->dev);

        $res = $this->send($this->progress(40))->assertCreated()
            ->assertJsonPath('data.type', 'progress')->assertJsonPath('data.percent', 40)->assertJsonPath('data.review_status', null)
            ->assertJsonPath('data.author.id', $this->dev->id)->assertJsonPath('milestone.status', 'in_progress')->assertJsonPath('milestone.percent', 40)
            ->assertJsonPath('project.status', 'process')->assertJsonPath('project.progress_percent', 20);

        $this->assertSame(MilestoneStatus::InProgress, $this->milestone->fresh()->status);
        $this->assertSame(ProjectStatus::Process, $this->project->fresh()->status);
        $this->assertSame(20, $this->project->fresh()->progress_percent);

        // R6.7: update dulu, baru perpindahan status oleh sistem
        $logs = ProjectActivity::where('project_id', $this->project->id)->orderBy('id')->get();
        $this->assertSame(['update.progress', 'status.changed'], $logs->pluck('type')->all());
        $this->assertSame($this->dev->id, $logs[0]->actor_id);
        $this->assertSame(['milestone_id' => $this->milestone->id, 'milestone_title' => 'Desain', 'update_id' => $res->json('data.id'), 'percent' => 40], $logs[0]->meta);
        $this->assertNull($logs[1]->actor_id);
        $this->assertSame(['from' => 'approved', 'to' => 'process', 'reason' => null], $logs[1]->meta);
    }

    public function test_progres_berikutnya_tidak_mengubah_status_dan_tidak_boleh_turun(): void
    {
        $this->actingAs($this->dev);
        $this->send($this->progress(40))->assertCreated();

        $this->send($this->progress(70))->assertCreated()->assertJsonPath('milestone.percent', 70);
        $this->send($this->progress(70))->assertCreated(); // sama boleh
        $this->send($this->progress(69))->assertStatus(422)->assertJsonValidationErrors(['percent' => 'Persentase tidak boleh lebih kecil dari progres terakhir (70%).']);

        $this->assertSame(70, $this->milestone->fresh()->percent);
        $this->assertSame(1, ProjectActivity::where('type', 'status.changed')->count());
        $this->assertSame(3, ProjectUpdate::count());
    }

    public function test_validasi_progres(): void
    {
        $this->actingAs($this->dev);

        $this->send(['type' => 'progress', 'note' => 'Catatan yang cukup panjang'])->assertStatus(422)->assertJsonValidationErrors(['percent' => 'Persentase wajib diisi (0–99).']);
        $this->send($this->progress(100))->assertStatus(422)->assertJsonValidationErrors('percent');
        $this->send($this->progress(-1))->assertStatus(422)->assertJsonValidationErrors('percent');
        $this->send($this->progress(40, ['percent' => '4.5']))->assertStatus(422)->assertJsonValidationErrors('percent');
        $this->send($this->progress(40, ['note' => 'pendek']))->assertStatus(422)->assertJsonValidationErrors(['note' => 'Catatan minimal 10 karakter.']);
        $this->send($this->progress(40, ['note' => str_repeat('x', 2001)]))->assertStatus(422)->assertJsonValidationErrors('note');
        $this->send($this->progress(40, ['type' => 'lain']))->assertStatus(422)->assertJsonValidationErrors('type');
        $this->send([])->assertStatus(422);
        $this->send($this->progress(0))->assertCreated(); // 0 diperbolehkan
        $this->assertSame(1, ProjectUpdate::count());
    }

    // ── Hasil ──

    public function test_hasil_menyerahkan_milestone_dan_mengirim_email_ke_klien(): void
    {
        Mail::fake();
        $this->actingAs($this->dev);

        $res = $this->send($this->resultPayload(['percent' => 5, 'files' => [$this->zip()]]))->assertCreated()
            ->assertJsonPath('data.type', 'result')->assertJsonPath('data.title', 'Desain versi 1')->assertJsonPath('data.percent', 100)
            ->assertJsonPath('data.review_status', 'pending')->assertJsonPath('milestone.status', 'submitted')->assertJsonPath('milestone.percent', 100)
            ->assertJsonPath('data.links.0', 'https://staging.contoh.test')->assertJsonPath('data.files.0.file_name', 'desain.zip')->assertJsonPath('data.files.0.file_type', 'application/zip');

        $update = ProjectUpdate::findOrFail($res->json('data.id'));
        $this->assertSame(ReviewStatus::Pending, $update->review_status);
        $this->assertSame(100, $update->percent, 'percent dari klien diabaikan');
        $this->assertSame(MilestoneStatus::Submitted, $this->milestone->fresh()->status);
        $this->assertSame(100, $this->project->fresh()->progress_percent);
        $this->assertSame(ProjectStatus::Process, $this->project->fresh()->status);
        $this->assertSame(['update.result', 'status.changed'], ProjectActivity::orderBy('id')->pluck('type')->all());
        $this->assertSame('Desain versi 1', ProjectActivity::where('type', 'update.result')->first()->meta['title']);

        Mail::assertQueued(ResultSubmittedMail::class, fn ($m) => $m->hasTo($this->project->user->email) && $m->milestone->id === $this->milestone->id);
    }

    public function test_hasil_wajib_judul_dan_berkas_atau_tautan(): void
    {
        $this->actingAs($this->dev);

        $this->send($this->resultPayload(['title' => null]))->assertStatus(422)->assertJsonValidationErrors(['title' => 'Judul hasil wajib diisi (3–100 karakter).']);
        $this->send($this->resultPayload(['title' => 'ab']))->assertStatus(422)->assertJsonValidationErrors('title');
        $this->send($this->resultPayload(['links' => []]))->assertStatus(422)
            ->assertJsonValidationErrors(['files' => 'Sertakan minimal satu berkas atau tautan hasil pengerjaan.']);
        $this->send(['type' => 'result', 'title' => 'Hasil', 'note' => 'Catatan yang cukup'])->assertStatus(422)->assertJsonValidationErrors('files');

        $this->send($this->resultPayload(['links' => []] + ['files' => [$this->zip()]]))->assertCreated(); // hanya berkas
        $this->assertSame(1, ProjectUpdate::count());
    }

    public function test_hasil_hanya_tautan_diperbolehkan(): void
    {
        $this->actingAs($this->dev);

        $this->send($this->resultPayload())->assertCreated();
    }

    // ── Tautan & berkas ──

    public function test_validasi_tautan(): void
    {
        $this->actingAs($this->dev);

        $this->send($this->progress(10, ['links' => ['http://tidak-aman.test']]))->assertStatus(422)->assertJsonValidationErrors(['links.0' => 'Tautan harus diawali https://']);
        $this->send($this->progress(10, ['links' => ['javascript:alert(1)']]))->assertStatus(422);
        $this->send($this->progress(10, ['links' => ['bukan url']]))->assertStatus(422);
        $this->send($this->progress(10, ['links' => ['https://contoh.test/'.str_repeat('a', 500)]]))->assertStatus(422)->assertJsonValidationErrors('links.0');
        $this->send($this->progress(10, ['links' => array_map(fn ($i) => "https://contoh.test/{$i}", range(1, 6))]))->assertStatus(422)->assertJsonValidationErrors(['links' => 'Maksimal 5 tautan.']);
        $this->send($this->progress(10, ['links' => array_map(fn ($i) => "https://contoh.test/{$i}", range(1, 5))]))->assertCreated();
    }

    public function test_validasi_berkas(): void
    {
        $this->actingAs($this->dev);
        $base = $this->progress(10);

        $this->send($base + ['files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]])
            ->assertStatus(422)->assertJsonValidationErrors(['files.0' => 'Tipe berkas harus PDF, PNG, JPG, atau ZIP.']);
        $this->send($base + ['files' => [UploadedFile::fake()->create('besar.zip', 25 * 1024 + 1, 'application/zip')]])
            ->assertStatus(422)->assertJsonValidationErrors(['files.0' => 'Setiap berkas maksimal 25 MB.']);
        $this->send($base + ['files' => array_map(fn ($i) => $this->zip("f{$i}.zip", 10), range(1, 11))])
            ->assertStatus(422)->assertJsonValidationErrors(['files' => 'Maksimal 10 berkas.']);
        $this->send($base + ['files' => array_map(fn ($i) => $this->zip("f{$i}.zip", 21 * 1024), range(1, 5))]) // 105 MB total
            ->assertStatus(422)->assertJsonValidationErrors(['files' => 'Total ukuran berkas maksimal 100 MB.']);
        $this->assertSame(0, ProjectUpdate::count());
        $this->assertSame([], Storage::disk('local')->allFiles());

        $res = $this->send($base + ['files' => [
            UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), UploadedFile::fake()->create('b.png', 10, 'image/png'),
            UploadedFile::fake()->create('c.jpg', 10, 'image/jpeg'), $this->zip(),
        ]])->assertCreated();
        $this->assertCount(4, $res->json('data.files'));
    }

    public function test_berkas_disimpan_privat_di_lokasi_yang_ditentukan(): void
    {
        $this->actingAs($this->dev);

        $res = $this->send($this->resultPayload(['files' => [$this->zip('desain final.zip')]]))->assertCreated();

        $attachment = ProjectAttachment::sole();
        $this->assertSame($res->json('data.id'), $attachment->update_id);
        $this->assertSame($this->dev->id, $attachment->uploaded_by);
        $this->assertSame('desain final.zip', $attachment->file_name);
        $this->assertStringStartsWith("attachments/{$this->project->id}/updates/{$attachment->update_id}/", $attachment->storage_path);
        Storage::disk('local')->assertExists($attachment->storage_path);
    }

    // ── Aturan status ──

    public function test_ditolak_untuk_milestone_menunggu_review_atau_disetujui(): void
    {
        $submitted = $this->milestone('Review', MilestoneStatus::Submitted, 100);
        $approved = $this->milestone('Selesai', MilestoneStatus::Approved, 100);
        $this->actingAs($this->dev);

        $this->send($this->progress(100 - 1), $submitted)->assertStatus(409)->assertJsonPath('message', 'Milestone sedang menunggu review klien.');
        $this->send($this->resultPayload(), $submitted)->assertStatus(409);
        $this->send($this->progress(50), $approved)->assertStatus(409)->assertJsonPath('message', 'Milestone ini sudah disetujui.');
        $this->assertSame(0, ProjectUpdate::count());
    }

    public function test_milestone_revisi_menerima_progres_dan_hasil_baru(): void
    {
        $revision = $this->milestone('Revisi', MilestoneStatus::Revision, 90);
        $this->actingAs($this->dev);

        $this->send($this->progress(89), $revision)->assertStatus(422); // dasar 90 (R2.6)
        $this->send($this->progress(95), $revision)->assertCreated()->assertJsonPath('milestone.status', 'in_progress');
        $this->send($this->resultPayload(), $revision)->assertCreated()->assertJsonPath('milestone.status', 'submitted');
    }

    public function test_ditolak_bila_status_proyek_atau_pembayaran_tidak_sesuai(): void
    {
        $done = $this->workspace($this->dev, ProjectStatus::Done);
        $unpaid = $this->workspace($this->dev, paid: false);
        $mDone = $this->milestone('A', project: $done);
        $mUnpaid = $this->milestone('B', project: $unpaid);
        $this->actingAs($this->dev);

        $this->send($this->progress(10), $mDone)->assertStatus(409)->assertJsonPath('message', 'Proyek tidak dapat menerima update pada status ini.');
        $this->send($this->progress(10), $mUnpaid)->assertStatus(409)->assertJsonPath('message', 'Workspace aktif setelah pembayaran terkonfirmasi.');
        $this->assertSame(0, ProjectUpdate::count());
    }

    public function test_proyek_yang_sudah_process_tetap_process(): void
    {
        $this->project->forceFill(['status' => ProjectStatus::Process])->save();
        $this->actingAs($this->dev);

        $this->send($this->progress(30))->assertCreated()->assertJsonPath('project.status', 'process');
        $this->assertSame(0, ProjectActivity::where('type', 'status.changed')->count());
    }

    // ── Izin ──

    public function test_izin_mengirim_update(): void
    {
        $this->send($this->progress())->assertUnauthorized();

        $this->actingAs($this->project->user)->postJson("/api/milestones/{$this->milestone->id}/updates", $this->progress())
            ->assertForbidden()->assertJsonPath('message', 'Hanya developer proyek yang dapat melakukan ini.');
        $this->actingAs($this->staff())->send($this->progress())->assertForbidden()->assertJsonPath('message', 'Anda tidak ditugaskan pada proyek ini.');
        $this->actingAs(User::factory()->admin()->create())->send($this->progress())->assertForbidden();
        $this->actingAs(User::factory()->create())->send($this->progress())->assertForbidden();

        // developer proyek lain tidak boleh menulis ke milestone proyek ini
        $other = $this->workspace($this->dev);
        $this->actingAs($this->staff())->send($this->progress(), $this->milestone('Lain', project: $other))->assertForbidden();

        $this->actingAs($this->dev)->send($this->progress(), $this->milestone('X'))->assertCreated();
        $this->postJson('/api/milestones/99999/updates', $this->progress())->assertNotFound();
        $this->assertSame(1, ProjectUpdate::count());
    }

    public function test_batas_laju_sepuluh_update_per_menit(): void
    {
        $this->actingAs($this->dev);

        for ($i = 0; $i < 10; $i++) {
            $this->send($this->progress(0))->assertCreated();
        }

        $this->send($this->progress(0))->assertStatus(429);
    }

    // ── Atomik (R2.15) ──

    public function test_kegagalan_membatalkan_seluruh_update_dan_membersihkan_berkas(): void
    {
        $this->app->instance(ProjectActivityLogger::class, new class extends ProjectActivityLogger
        {
            public function log(Project $project, string $type, array $meta = [], ?User $actor = null, ?CarbonInterface $at = null): ProjectActivity
            {
                throw new RuntimeException('log gagal');
            }
        });
        $this->actingAs($this->dev);
        $this->withoutExceptionHandling();

        try {
            $this->send($this->resultPayload(['files' => [$this->zip(), $this->zip('dua.zip')]]));
            $this->fail('Seharusnya melempar exception');
        } catch (RuntimeException $e) {
            $this->assertSame('log gagal', $e->getMessage());
        }

        $this->assertSame(0, ProjectUpdate::count());
        $this->assertSame(0, ProjectAttachment::count());
        $this->assertSame([], Storage::disk('local')->allFiles(), 'berkas yang telanjur ditulis dihapus');
        $this->assertSame(MilestoneStatus::Planned, $this->milestone->fresh()->status);
        $this->assertSame(ProjectStatus::Approved, $this->project->fresh()->status);
        $this->assertSame(0, $this->project->fresh()->progress_percent);
    }

    // ── Daftar update ──

    public function test_peserta_membaca_daftar_update_dengan_paginasi_dan_filter(): void
    {
        $this->actingAs($this->dev);
        $this->send($this->progress(10))->assertCreated();
        $this->send($this->progress(20, ['links' => ['https://a.test']]))->assertCreated();
        $this->send($this->resultPayload(['files' => [$this->zip()]]))->assertCreated();
        $url = "/api/milestones/{$this->milestone->id}/updates";

        foreach ([$this->project->user, $this->dev] as $viewer) {
            $res = $this->actingAs($viewer)->getJson($url)->assertOk()->assertJsonCount(3, 'data');
            $this->assertSame(['result', 'progress', 'progress'], array_column($res->json('data'), 'type'), 'terbaru dulu');
            $this->assertFalse($res->json('meta.has_more'));
        }

        $this->getJson("{$url}?limit=2")->assertJsonCount(2, 'data')->assertJsonPath('meta.has_more', true);
        $this->getJson("{$url}?type=progress")->assertJsonCount(2, 'data');
        $firstId = $this->getJson("{$url}?limit=1")->json('data.0.id');
        $this->getJson("{$url}?before_id={$firstId}")->assertJsonCount(2, 'data');
        $this->getJson($url)->assertJsonPath('data.0.files.0.file_name', 'desain.zip');

        auth()->logout();
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($this->staff())->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
    }

    // ── Berkas ──

    public function test_unduh_berkas_update_hanya_untuk_peserta(): void
    {
        $this->actingAs($this->dev);
        $this->send($this->resultPayload(['files' => [$this->zip('desain.zip')]]))->assertCreated();
        $attachment = ProjectAttachment::sole();
        $url = "/api/attachments/download/{$attachment->id}";

        foreach ([$this->project->user, $this->dev] as $viewer) {
            $res = $this->actingAs($viewer)->get($url)->assertOk();
            $this->assertStringContainsString('desain.zip', $res->headers->get('Content-Disposition'));
        }

        $this->actingAs($this->staff())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_daftar_lampiran_proyek_tidak_memuat_berkas_update(): void
    {
        $this->actingAs($this->dev);
        $this->send($this->resultPayload(['files' => [$this->zip()]]))->assertCreated();

        $this->actingAs($this->project->user)->getJson("/api/attachments/{$this->project->id}")->assertOk()->assertJsonCount(0, 'data');
    }

    // ── Realtime ──

    public function test_update_disiarkan_dan_proyek_diperbarui(): void
    {
        Event::fake([ProjectUpdateSubmitted::class, MilestoneChanged::class, ProjectUpdated::class]);
        $this->actingAs($this->dev);

        $this->send($this->resultPayload(['files' => [$this->zip()]]))->assertCreated();

        Event::assertDispatched(ProjectUpdateSubmitted::class, function ($e) {
            $payload = $e->broadcastWith();

            return array_map(fn ($c) => $c->name, $e->broadcastOn()) === ["private-project.{$this->project->id}"]
                && $e->broadcastAs() === 'update.created'
                && $payload['type'] === 'result' && $payload['review_status'] === 'pending'
                && $payload['author']['id'] === $this->dev->id && $payload['files'][0]['file_name'] === 'desain.zip'
                && ! array_key_exists('storage_path', $payload['files'][0]);
        });
        Event::assertDispatched(MilestoneChanged::class);
        Event::assertDispatched(ProjectUpdated::class, fn ($e) => $e->broadcastWith()['progress_percent'] === 100 && $e->broadcastWith()['status'] === 'process');
    }
}
