<?php

namespace Tests\Feature;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Models\ChatMessage;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectComment;
use App\Models\ProjectMilestone;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Services\ProgressCalculator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Regresi lintas fitur Project Workspace: alur penuh lewat API, invarian data seeder, dan sapuan IDOR. */
class WorkspaceRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function staff(Role $role = Role::Developer): User
    {
        $u = User::factory()->create();
        $u->role = $role;
        $u->save();

        return $u;
    }

    /** Proyek berbayar yang sedang berjalan dengan satu milestone `submitted` dan satu komentar, pesan chat. */
    private function busyProject(User $dev): Project
    {
        $p = Project::factory()->create(['plan_price_range' => '3000000']);
        $p->status = ProjectStatus::Process;
        $p->developer_id = $dev->id;
        $p->save();
        Payment::factory()->for($p)->paid()->create(['amount' => 3000000]);

        $m = new ProjectMilestone(['title' => 'Desain']);
        $m->project_id = $p->id;
        $m->position = 1;
        $m->status = MilestoneStatus::Submitted;
        $m->percent = 100;
        $m->save();

        $u = new ProjectUpdate;
        $u->project_id = $p->id;
        $u->milestone_id = $m->id;
        $u->user_id = $dev->id;
        $u->type = ProjectUpdate::TYPE_RESULT;
        $u->title = 'Hasil';
        $u->note = 'Catatan hasil';
        $u->percent = 100;
        $u->links = [];
        $u->review_status = ReviewStatus::Pending;
        $u->save();

        $c = new ProjectComment;
        $c->project_id = $p->id;
        $c->user_id = $p->user_id;
        $c->body = 'komentar';
        $c->save();

        $msg = new ChatMessage;
        $msg->project_id = $p->id;
        $msg->user_id = $dev->id;
        $msg->body = 'pesan';
        $msg->save();

        return $p;
    }

    public function test_alur_penuh_dari_pengajuan_sampai_proyek_selesai(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $dev = $this->staff();
        $project = Project::factory()->create(['plan_price_range' => '3000000']);
        $client = $project->user;

        // Admin menyetujui dan menugaskan developer; belum dibayar -> workspace terkunci.
        $this->actingAs($admin)->patchJson("/api/projects/{$project->id}/status", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/projects/{$project->id}/developer", ['developer_id' => $dev->id])->assertOk();
        $this->actingAs($dev)->postJson("/api/projects/{$project->id}/milestones", ['title' => 'Desain'])->assertStatus(409);
        $this->actingAs($client)->postJson("/api/projects/{$project->id}/messages", ['body' => 'halo'])->assertStatus(409);

        Payment::factory()->for($project)->paid()->create(['amount' => 3000000]);

        // Developer menyusun dua milestone dan mengerjakannya.
        $this->actingAs($dev);
        $m1 = $this->postJson("/api/projects/{$project->id}/milestones", ['title' => 'Desain'])->assertCreated()->json('data.id');
        $m2 = $this->postJson("/api/projects/{$project->id}/milestones", ['title' => 'Pengembangan'])->assertCreated()->json('data.id');
        $this->postJson("/api/milestones/{$m1}/updates", ['type' => 'progress', 'percent' => 50, 'note' => 'Wireframe selesai'])->assertCreated()->assertJsonPath('project.status', 'process');
        $r1 = $this->postJson("/api/milestones/{$m1}/updates", ['type' => 'result', 'title' => 'Desain v1', 'note' => 'Mockup siap', 'links' => ['https://staging.contoh.test']])->assertCreated()->json('data.id');

        // Klien meminta revisi; developer menyerahkan ulang; klien menyetujui.
        $this->actingAs($client)->postJson("/api/updates/{$r1}/request-revision", ['reason' => 'Warna tombol belum sesuai dengan brand kami.'])->assertOk();
        $this->actingAs($dev)->postJson("/api/milestones/{$m1}/updates", ['type' => 'result', 'title' => 'Desain v2', 'note' => 'Warna sudah diperbaiki', 'links' => ['https://staging.contoh.test']])->assertCreated();
        $r1b = ProjectUpdate::where('milestone_id', $m1)->latest('id')->value('id');
        $this->actingAs($client)->postJson("/api/updates/{$r1b}/approve")->assertOk()->assertJsonPath('data.project.status', 'process')->assertJsonPath('data.project.progress_percent', 50);

        // Diskusi dan chat berjalan paralel.
        $this->postJson("/api/projects/{$project->id}/comments", ['body' => 'Terima kasih!'])->assertCreated();
        $this->postJson("/api/projects/{$project->id}/messages", ['body' => 'Kapan tahap berikutnya?'])->assertCreated();
        $this->actingAs($dev)->postJson("/api/projects/{$project->id}/messages", ['body' => 'Minggu depan.'])->assertCreated();

        // Milestone terakhir -> proyek selesai otomatis, chat menjadi hanya-baca.
        $r2 = $this->postJson("/api/milestones/{$m2}/updates", ['type' => 'result', 'title' => 'Aplikasi', 'note' => 'Seluruh fitur selesai', 'links' => ['https://staging.contoh.test']])->assertCreated()->json('data.id');
        $this->actingAs($client)->postJson("/api/updates/{$r2}/approve")->assertOk()->assertJsonPath('data.project.status', 'done')->assertJsonPath('data.project.progress_percent', 100);
        $this->postJson("/api/projects/{$project->id}/messages", ['body' => 'terlambat'])->assertStatus(409);
        $this->getJson("/api/projects/{$project->id}/messages")->assertOk()->assertJsonCount(2, 'data');
        $this->postJson("/api/projects/{$project->id}/comments", ['body' => 'Komentar tetap boleh'])->assertCreated();

        $project->refresh();
        $this->assertSame(ProjectStatus::Done, $project->status);
        $this->assertNotNull($project->completed_at);
        $this->assertSame(1, $project->revisions_used);

        // Riwayat aktivitas lengkap, berurutan, dan hanya satu status.changed per perpindahan.
        $types = ProjectActivity::where('project_id', $project->id)->orderBy('id')->pluck('type')->all();
        $this->assertSame('status.changed', $types[0]);
        $this->assertContains('developer.assigned', $types);
        foreach (['milestone.created', 'update.progress', 'update.result', 'review.revision_requested', 'review.approved', 'chat.started'] as $t) {
            $this->assertContains($t, $types, $t);
        }
        $this->assertSame(1, count(array_keys($types, 'chat.started')));
        $this->assertSame(['approved', 'process', 'done'], ProjectActivity::where('project_id', $project->id)->where('type', 'status.changed')->orderBy('id')->get()->map(fn ($a) => $a->meta['to'])->all());
    }

    public function test_data_seeder_konsisten(): void
    {
        $this->seed(DatabaseSeeder::class);
        $calculator = app(ProgressCalculator::class);

        foreach (Project::with('milestones')->get() as $p) {
            $this->assertSame($calculator->calculate($p), $p->progress_percent, "progres #{$p->id}");
            $this->assertSame($p->revisions()->count(), $p->revisions_used, "kuota terpakai #{$p->id}");
            $this->assertLessThanOrEqual($p->revision_limit ?? PHP_INT_MAX, $p->revisions_used);

            foreach ($p->milestones as $m) {
                if ($m->status === MilestoneStatus::Approved) {
                    $this->assertSame(100, $m->percent);
                    $this->assertNotNull($m->approved_at);
                }
            }

            if ($p->status === ProjectStatus::Done) {
                $this->assertTrue($p->milestones->isNotEmpty() && $p->milestones->every(fn ($m) => $m->status === MilestoneStatus::Approved));
                $this->assertNotNull($p->completed_at);
            }

            // Setiap proyek dengan chat mencatat `chat.started` tepat sekali.
            $this->assertSame($p->chatMessages()->exists() ? 1 : 0, $p->activities()->where('type', 'chat.started')->count());
        }

        $this->assertSame(1, Project::where('project_title', 'Aplikasi toko online demo')->count());
        $this->assertSame(ProjectStatus::Done, Project::where('project_title', 'Landing page selesai demo')->firstOrFail()->status);
    }

    public function test_seeder_dapat_dijalankan_ulang_tanpa_menggandakan_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $count = [Project::count(), ProjectMilestone::count(), ChatMessage::count()];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($count, [Project::count(), ProjectMilestone::count(), ChatMessage::count()]);
    }

    /** Peserta proyek A tidak dapat menyentuh sumber daya proyek B lewat endpoint mana pun. */
    public function test_sapuan_idor_lintas_proyek(): void
    {
        Storage::fake('local');
        $devA = $this->staff();
        $devB = $this->staff();
        $a = $this->busyProject($devA);
        $b = $this->busyProject($devB);

        $milestoneB = $b->milestones()->firstOrFail();
        $updateB = $b->updates()->firstOrFail();
        $commentB = $b->comments()->firstOrFail();
        $messageB = $b->chatMessages()->firstOrFail();

        $requests = [
            ['GET', "/api/projects/{$b->id}/milestones"], ['POST', "/api/projects/{$b->id}/milestones", ['title' => 'x y z']],
            ['PATCH', "/api/projects/{$b->id}/milestones/order", ['ids' => [$milestoneB->id]]],
            ['PATCH', "/api/milestones/{$milestoneB->id}", ['due_date' => '2099-01-01']], ['DELETE', "/api/milestones/{$milestoneB->id}"],
            ['GET', "/api/milestones/{$milestoneB->id}/updates"], ['POST', "/api/milestones/{$milestoneB->id}/updates", ['type' => 'progress', 'percent' => 1, 'note' => 'catatan panjang']],
            ['GET', "/api/projects/{$b->id}/activities"],
            ['GET', "/api/projects/{$b->id}/comments"], ['POST', "/api/projects/{$b->id}/comments", ['body' => 'x']], ['DELETE', "/api/comments/{$commentB->id}"],
            ['POST', "/api/updates/{$updateB->id}/approve"], ['POST', "/api/updates/{$updateB->id}/request-revision", ['reason' => str_repeat('a', 25)]],
            ['GET', "/api/projects/{$b->id}/revisions"], ['PATCH', "/api/projects/{$b->id}/revision-limit", ['limit' => 9]],
            ['GET', "/api/projects/{$b->id}/messages"], ['POST', "/api/projects/{$b->id}/messages", ['body' => 'x']],
            ['DELETE', "/api/messages/{$messageB->id}"], ['POST', "/api/projects/{$b->id}/messages/read", ['last_message_id' => 1]],
        ];

        // Klien A, developer A, admin tanpa penugasan, dan pengguna lain: semuanya harus ditolak di proyek B.
        foreach ([$a->user, $devA, User::factory()->admin()->create(), User::factory()->create()] as $actor) {
            foreach ($requests as $request) {
                [$method, $url] = $request;
                $body = $request[2] ?? [];
                $this->actingAs($actor)->json($method, $url, $body)->assertForbidden("{$method} {$url} oleh #{$actor->id}");
            }
        }

        // Tidak ada yang berubah di proyek B.
        $this->assertSame(1, $b->milestones()->count());
        $this->assertSame(ReviewStatus::Pending, $updateB->fresh()->review_status);
        $this->assertSame(0, $b->fresh()->revisions_used);
        $this->assertSame(3, $b->fresh()->revision_limit);
        $this->assertNotSoftDeleted($commentB);
        $this->assertNotSoftDeleted($messageB);

        // Tamu ditolak di semua endpoint.
        auth()->logout();
        foreach ($requests as $request) {
            [$method, $url] = $request;
            $body = $request[2] ?? [];
            $this->json($method, $url, $body)->assertUnauthorized();
        }
    }
}
