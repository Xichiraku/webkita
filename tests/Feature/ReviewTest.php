<?php

namespace Tests\Feature;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\ReviewStatus;
use App\Enums\RevisionStatus;
use App\Enums\Role;
use App\Events\MilestoneChanged;
use App\Events\ProjectCommentPosted;
use App\Events\ProjectUpdated;
use App\Events\UpdateReviewed;
use App\Mail\MilestoneApprovedMail;
use App\Mail\ProjectCompletedMail;
use App\Mail\RevisionRequestedMail;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectComment;
use App\Models\ProjectMilestone;
use App\Models\ProjectRevision;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Services\ProjectActivityLogger;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    private Project $project;

    private ProjectMilestone $milestone;

    private ProjectUpdate $result;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dev = User::factory()->create();
        $this->dev->role = Role::Developer;
        $this->dev->save();

        $this->project = Project::factory()->create(['plan_price_range' => '3000000']);
        $this->project->status = ProjectStatus::Process;
        $this->project->developer_id = $this->dev->id;
        $this->project->save();
        Payment::factory()->for($this->project)->paid()->create(['amount' => 3000000]);

        [$this->milestone, $this->result] = $this->submitted('Desain');
    }

    /** @return array{ProjectMilestone, ProjectUpdate} milestone `submitted` dengan hasil `pending` */
    private function submitted(string $title, ?Project $project = null): array
    {
        $project ??= $this->project;

        $m = new ProjectMilestone(['title' => $title]);
        $m->project_id = $project->id;
        $m->position = $project->milestones()->count() + 1;
        $m->status = MilestoneStatus::Submitted;
        $m->percent = 100;
        $m->save();

        $u = new ProjectUpdate;
        $u->project_id = $project->id;
        $u->milestone_id = $m->id;
        $u->user_id = $project->developer_id;
        $u->type = ProjectUpdate::TYPE_RESULT;
        $u->title = "Hasil {$title}";
        $u->note = 'Catatan hasil pengerjaan';
        $u->percent = 100;
        $u->links = [];
        $u->review_status = ReviewStatus::Pending;
        $u->save();

        return [$m, $u];
    }

    private function approve(?ProjectUpdate $update = null)
    {
        return $this->postJson('/api/updates/'.($update ?? $this->result)->id.'/approve');
    }

    private function revise(string $reason = 'Warna logo masih salah, tolong sesuaikan dengan brand.', ?ProjectUpdate $update = null)
    {
        return $this->postJson('/api/updates/'.($update ?? $this->result)->id.'/request-revision', ['reason' => $reason]);
    }

    private function client(): User
    {
        return $this->project->user;
    }

    // ── Persetujuan ──

    public function test_klien_menyetujui_hasil_dan_proyek_selesai_bila_milestone_terakhir(): void
    {
        Mail::fake();
        Event::fake([UpdateReviewed::class, MilestoneChanged::class, ProjectUpdated::class]);
        $this->actingAs($this->client());

        $this->approve()->assertOk()
            ->assertJsonPath('data.update.review_status', 'approved')->assertJsonPath('data.milestone.status', 'approved')->assertJsonPath('data.milestone.percent', 100)
            ->assertJsonPath('data.project.status', 'done')->assertJsonPath('data.project.progress_percent', 100);

        $project = $this->project->fresh();
        $this->assertSame(ProjectStatus::Done, $project->status);
        $this->assertNotNull($project->completed_at);
        $this->assertSame(ReviewStatus::Approved, $this->result->fresh()->review_status);
        $this->assertSame($this->client()->id, $this->result->fresh()->reviewed_by);
        $this->assertNotNull($this->milestone->fresh()->approved_at);

        $logs = ProjectActivity::orderBy('id')->get();
        $this->assertSame(['review.approved', 'status.changed'], $logs->pluck('type')->all());
        $this->assertSame($this->client()->id, $logs[0]->actor_id);
        $this->assertNull($logs[1]->actor_id);
        $this->assertSame(['from' => 'process', 'to' => 'done', 'reason' => null], $logs[1]->meta);

        Event::assertDispatched(UpdateReviewed::class);
        Event::assertDispatched(MilestoneChanged::class);
        Event::assertDispatched(ProjectUpdated::class);
        Mail::assertQueued(MilestoneApprovedMail::class, fn ($m) => $m->hasTo($this->dev->email));
        Mail::assertQueued(ProjectCompletedMail::class, 2);
        Mail::assertQueued(ProjectCompletedMail::class, fn ($m) => $m->hasTo($this->client()->email));
    }

    public function test_proyek_belum_selesai_bila_masih_ada_milestone_lain(): void
    {
        Mail::fake();
        $other = new ProjectMilestone(['title' => 'Lanjutan']);
        $other->project_id = $this->project->id;
        $other->position = 2;
        $other->save();
        $this->actingAs($this->client());

        $this->approve()->assertOk()->assertJsonPath('data.project.status', 'process')->assertJsonPath('data.project.progress_percent', 50);

        $this->assertSame(ProjectStatus::Process, $this->project->fresh()->status);
        $this->assertNull($this->project->fresh()->completed_at);
        Mail::assertNotQueued(ProjectCompletedMail::class);
        Mail::assertQueued(MilestoneApprovedMail::class);
    }

    public function test_persetujuan_ganda_ditolak(): void
    {
        $this->actingAs($this->client());

        $this->approve()->assertOk();
        $this->approve()->assertStatus(409)->assertJsonPath('message', 'Tidak ada hasil yang sedang menunggu review.');
        $this->assertSame(1, ProjectActivity::where('type', 'review.approved')->count());
    }

    public function test_hanya_hasil_pending_pada_milestone_submitted_dapat_ditinjau(): void
    {
        $progress = new ProjectUpdate;
        $progress->project_id = $this->project->id;
        $progress->milestone_id = $this->milestone->id;
        $progress->user_id = $this->dev->id;
        $progress->type = ProjectUpdate::TYPE_PROGRESS;
        $progress->note = 'Progres biasa saja';
        $progress->percent = 50;
        $progress->links = [];
        $progress->save();
        $this->actingAs($this->client());

        $this->approve($progress)->assertStatus(409);
        $this->revise(update: $progress)->assertStatus(409);

        $this->milestone->forceFill(['status' => MilestoneStatus::InProgress])->save();
        $this->approve()->assertStatus(409);
        $this->revise()->assertStatus(409);
    }

    // ── Revisi ──

    public function test_klien_meminta_revisi(): void
    {
        Mail::fake();
        Event::fake([UpdateReviewed::class, MilestoneChanged::class, ProjectCommentPosted::class, ProjectUpdated::class]);
        $this->actingAs($this->client());
        $reason = 'Warna logo masih salah, tolong sesuaikan dengan brand.';

        $this->revise($reason)->assertOk()
            ->assertJsonPath('data.update.review_status', 'revision_requested')->assertJsonPath('data.milestone.status', 'revision')->assertJsonPath('data.milestone.percent', 90)
            ->assertJsonPath('data.project.status', 'process')->assertJsonPath('data.project.revisions_used', 1)->assertJsonPath('data.project.revision_limit', 3)->assertJsonPath('data.project.progress_percent', 90)
            ->assertJsonPath('data.revision.number', 1)->assertJsonPath('data.revision.status', 'open');

        $revision = ProjectRevision::sole();
        $this->assertSame([1, $this->milestone->id, $this->result->id, $reason, RevisionStatus::Open], [$revision->number, $revision->milestone_id, $revision->update_id, $revision->reason, $revision->status]);
        $this->assertSame(1, $this->project->fresh()->revisions_used);
        $this->assertSame(ReviewStatus::RevisionRequested, $this->result->fresh()->review_status);

        $comment = ProjectComment::sole();
        $this->assertSame([$reason, $this->result->id, $this->client()->id], [$comment->body, $comment->update_id, $comment->user_id]);

        $log = ProjectActivity::sole();
        $this->assertSame('review.revision_requested', $log->type);
        $this->assertSame(1, $log->meta['revision_number']);

        Event::assertDispatched(UpdateReviewed::class);
        Event::assertDispatched(ProjectCommentPosted::class);
        Mail::assertQueued(RevisionRequestedMail::class, fn ($m) => $m->hasTo($this->dev->email) && $m->revision->number === 1);
    }

    public function test_validasi_alasan_revisi(): void
    {
        $this->actingAs($this->client());

        $this->revise('terlalu pendek')->assertStatus(422)->assertJsonValidationErrors(['reason' => 'Jelaskan bagian yang perlu direvisi (minimal 20 karakter).']);
        $this->revise('')->assertStatus(422);
        $this->revise(str_repeat('x', 2001))->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->assertSame(0, ProjectRevision::count());
        $this->assertSame(0, $this->project->fresh()->revisions_used);
    }

    public function test_kuota_revisi_proyek_habis(): void
    {
        $this->project->forceFill(['revision_limit' => 1, 'revisions_used' => 1])->save();
        $this->actingAs($this->client());

        $this->revise()->assertStatus(409)->assertJsonPath('message', 'Kuota revisi proyek (1) sudah habis. Setujui hasil atau sepakati tambahan kuota dengan developer.');
        $this->assertSame(MilestoneStatus::Submitted, $this->milestone->fresh()->status);
        $this->assertSame(0, ProjectRevision::count());

        $this->approve()->assertOk(); // klien tetap boleh menyetujui
    }

    public function test_kuota_kosong_berarti_tanpa_batas(): void
    {
        $this->project->forceFill(['revision_limit' => null, 'revisions_used' => 40])->save();
        $this->actingAs($this->client());

        $this->revise()->assertOk()->assertJsonPath('data.revision.number', 41);
    }

    public function test_hasil_pengganti_menutup_revisi_tetapi_progres_biasa_tidak(): void
    {
        $this->actingAs($this->client());
        $this->revise()->assertOk();
        $this->actingAs($this->dev);

        $this->postJson("/api/milestones/{$this->milestone->id}/updates", ['type' => 'progress', 'percent' => 95, 'note' => 'Sedang memperbaiki logo'])->assertCreated();
        $this->assertSame(RevisionStatus::Open, ProjectRevision::sole()->status);

        $res = $this->postJson("/api/milestones/{$this->milestone->id}/updates", ['type' => 'result', 'title' => 'Desain v2', 'note' => 'Logo sudah diperbaiki', 'links' => ['https://staging.contoh.test']])->assertCreated();

        $revision = ProjectRevision::sole();
        $this->assertSame(RevisionStatus::Resolved, $revision->status);
        $this->assertSame($res->json('data.id'), $revision->resolved_by_update_id);
        $this->assertNotNull($revision->resolved_at);
        $this->assertSame(MilestoneStatus::Submitted, $this->milestone->fresh()->status);

        $this->actingAs($this->client());
        $this->revise('Masih ada yang kurang pada bagian footer halaman.', ProjectUpdate::find($res->json('data.id')))->assertOk()->assertJsonPath('data.revision.number', 2);
    }

    public function test_kegagalan_membatalkan_seluruh_revisi(): void
    {
        $this->app->instance(ProjectActivityLogger::class, new class extends ProjectActivityLogger
        {
            public function log(Project $project, string $type, array $meta = [], ?User $actor = null, ?CarbonInterface $at = null): ProjectActivity
            {
                throw new RuntimeException('log gagal');
            }
        });
        $this->actingAs($this->client());
        $this->withoutExceptionHandling();

        try {
            $this->revise();
            $this->fail('Seharusnya melempar exception');
        } catch (RuntimeException) {
        }

        $this->assertSame(0, $this->project->fresh()->revisions_used);
        $this->assertSame(0, ProjectRevision::count());
        $this->assertSame(0, ProjectComment::count());
        $this->assertSame(ReviewStatus::Pending, $this->result->fresh()->review_status);
        $this->assertSame(MilestoneStatus::Submitted, $this->milestone->fresh()->status);
        $this->assertSame(100, $this->milestone->fresh()->percent);
    }

    // ── Izin ──

    public function test_izin_peninjauan(): void
    {
        $this->approve()->assertUnauthorized();

        $this->actingAs($this->dev);
        $this->approve()->assertForbidden()->assertJsonPath('message', 'Hanya pemilik proyek yang dapat meninjau hasil.');
        $this->revise()->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $this->approve()->assertForbidden();
        $this->revise()->assertForbidden();

        $this->actingAs(User::factory()->create());
        $this->approve()->assertForbidden();
        $this->revise()->assertForbidden();

        $this->postJson('/api/updates/99999/approve')->assertNotFound();
        $this->assertSame(ReviewStatus::Pending, $this->result->fresh()->review_status);
    }

    public function test_peninjauan_butuh_pembayaran_terkonfirmasi(): void
    {
        $this->project->payments()->delete();
        $this->actingAs($this->client());

        $this->approve()->assertStatus(409);
        $this->assertSame(ReviewStatus::Pending, $this->result->fresh()->review_status);
    }

    // ── Kuota ──

    public function test_developer_mengubah_kuota_revisi(): void
    {
        Event::fake([ProjectUpdated::class]);
        $this->project->forceFill(['revisions_used' => 2])->save();
        $this->actingAs($this->dev);

        $this->patchJson("/api/projects/{$this->project->id}/revision-limit", ['limit' => 5])->assertOk()->assertJsonPath('data.revision_limit', 5);
        $log = ProjectActivity::sole();
        $this->assertSame(['revision.limit_changed', $this->dev->id, ['from' => 3, 'to' => 5]], [$log->type, $log->actor_id, $log->meta]);

        $this->patchJson("/api/projects/{$this->project->id}/revision-limit", ['limit' => null])->assertOk()->assertJsonPath('data.revision_limit', null);
        $this->assertNull($this->project->fresh()->revision_limit);

        $this->patchJson("/api/projects/{$this->project->id}/revision-limit", ['limit' => null])->assertOk();
        $this->assertSame(2, ProjectActivity::count(), 'nilai sama tidak dicatat');
    }

    public function test_validasi_kuota_revisi(): void
    {
        $this->project->forceFill(['revisions_used' => 2])->save();
        $this->actingAs($this->dev);
        $url = "/api/projects/{$this->project->id}/revision-limit";

        $this->patchJson($url, ['limit' => 1])->assertStatus(422)->assertJsonValidationErrors(['limit' => 'Kuota tidak boleh lebih kecil dari revisi yang sudah dipakai (2).']);
        $this->patchJson($url, ['limit' => 51])->assertStatus(422);
        $this->patchJson($url, ['limit' => -1])->assertStatus(422);
        $this->patchJson($url, ['limit' => 'abc'])->assertStatus(422);
        $this->patchJson($url, [])->assertStatus(422);
        $this->assertSame(3, $this->project->fresh()->revision_limit);
    }

    public function test_izin_mengubah_kuota(): void
    {
        $url = "/api/projects/{$this->project->id}/revision-limit";

        $this->patchJson($url, ['limit' => 9])->assertUnauthorized();
        $this->actingAs($this->client())->patchJson($url, ['limit' => 9])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->patchJson($url, ['limit' => 9])->assertForbidden();
        $this->actingAs(User::factory()->create())->patchJson($url, ['limit' => 9])->assertForbidden();
        $this->assertSame(3, $this->project->fresh()->revision_limit);
    }

    // ── Daftar revisi ──

    public function test_peserta_melihat_daftar_revisi(): void
    {
        $this->actingAs($this->client());
        $this->revise()->assertOk();
        $url = "/api/projects/{$this->project->id}/revisions";

        foreach ([$this->client(), $this->dev] as $viewer) {
            $this->actingAs($viewer)->getJson($url)->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.number', 1)->assertJsonPath('data.0.milestone_title', 'Desain')->assertJsonPath('data.0.status', 'open');
        }

        $this->actingAs(User::factory()->admin()->create())->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
    }
}
