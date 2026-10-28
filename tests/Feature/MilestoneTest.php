<?php

namespace Tests\Feature;

use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Events\MilestoneChanged;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectMilestone;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Services\ProgressCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MilestoneTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dev = $this->staff();
        $this->project = $this->workspace($this->dev);
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

    private function milestone(string $title = 'Desain', MilestoneStatus $status = MilestoneStatus::Planned, int $percent = 0, ?Project $project = null): ProjectMilestone
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

    private function createMilestone(array $data = ['title' => 'Desain']): TestResponse
    {
        return $this->postJson("/api/projects/{$this->project->id}/milestones", $data);
    }

    // ── Membuat ──

    public function test_developer_membuat_milestone_dengan_urutan_otomatis(): void
    {
        $this->actingAs($this->dev);

        $a = $this->createMilestone(['title' => 'Desain', 'description' => 'Wireframe dan mockup', 'due_date' => now()->addWeek()->toDateString()])
            ->assertCreated()->assertJsonPath('data.position', 1)->assertJsonPath('data.status', 'planned')->assertJsonPath('data.percent', 0)
            ->assertJsonPath('data.status_label', 'Direncanakan')->assertJsonPath('data.updates_count', 0);
        $b = $this->createMilestone(['title' => 'Pengembangan'])->assertCreated()->assertJsonPath('data.position', 2);

        $this->assertSame(['milestone.created', 'milestone.created'], ProjectActivity::where('project_id', $this->project->id)->orderBy('id')->pluck('type')->all());
        $log = ProjectActivity::where('type', 'milestone.created')->first();
        $this->assertSame($this->dev->id, $log->actor_id);
        $this->assertSame(['milestone_id' => $a->json('data.id'), 'title' => 'Desain'], $log->meta);
        $this->assertSame(2, $this->project->milestones()->count());
        $this->assertNotSame($a->json('data.id'), $b->json('data.id'));
    }

    public function test_izin_membuat_milestone(): void
    {
        $this->createMilestone()->assertUnauthorized();

        $this->actingAs($this->project->user)->createMilestone()->assertForbidden()->assertJsonPath('message', 'Hanya developer proyek yang dapat melakukan ini.');
        $this->actingAs($this->staff())->createMilestone()->assertForbidden()->assertJsonPath('message', 'Anda tidak ditugaskan pada proyek ini.');
        $this->actingAs(User::factory()->admin()->create())->createMilestone()->assertForbidden();
        $this->actingAs(User::factory()->create())->createMilestone()->assertForbidden();
        $this->assertSame(0, ProjectMilestone::count());
    }

    public function test_admin_yang_ditugaskan_boleh_menyusun_milestone(): void
    {
        $admin = User::factory()->admin()->create();
        $project = $this->workspace($admin);

        $this->actingAs($admin)->postJson("/api/projects/{$project->id}/milestones", ['title' => 'Desain'])->assertCreated();
    }

    public function test_milestone_hanya_saat_workspace_aktif_dan_proyek_berjalan(): void
    {
        $unpaid = $this->workspace($this->dev, paid: false);
        $done = $this->workspace($this->dev, ProjectStatus::Done);
        $this->actingAs($this->dev);

        $this->postJson("/api/projects/{$unpaid->id}/milestones", ['title' => 'Desain'])
            ->assertStatus(409)->assertJsonPath('message', 'Workspace aktif setelah pembayaran terkonfirmasi.');
        $this->postJson("/api/projects/{$done->id}/milestones", ['title' => 'Desain'])
            ->assertStatus(409)->assertJsonPath('message', 'Milestone hanya dapat dikelola pada proyek yang sedang berjalan.');
    }

    public function test_validasi_milestone(): void
    {
        $this->actingAs($this->dev);

        $this->createMilestone(['title' => 'ab'])->assertStatus(422)->assertJsonValidationErrors(['title' => 'Judul milestone minimal 3 karakter.']);
        $this->createMilestone([])->assertStatus(422)->assertJsonValidationErrors(['title' => 'Judul milestone wajib diisi.']);
        $this->createMilestone(['title' => str_repeat('x', 101)])->assertStatus(422)->assertJsonValidationErrors('title');
        $this->createMilestone(['title' => 'Desain', 'description' => str_repeat('x', 1001)])->assertStatus(422)->assertJsonValidationErrors('description');
        $this->createMilestone(['title' => 'Desain', 'due_date' => now()->subDay()->toDateString()])->assertStatus(422)->assertJsonValidationErrors(['due_date' => 'Tenggat tidak boleh di masa lalu.']);
        $this->createMilestone(['title' => 'Desain', 'due_date' => 'bukan-tanggal'])->assertStatus(422);
        $this->createMilestone(['title' => 'Desain', 'due_date' => now()->toDateString()])->assertCreated(); // hari ini boleh
    }

    public function test_maksimal_sepuluh_milestone(): void
    {
        $this->actingAs($this->dev);
        for ($i = 1; $i <= 10; $i++) {
            $this->createMilestone(['title' => "Tahap {$i}"])->assertCreated();
        }

        $this->createMilestone(['title' => 'Tahap 11'])->assertStatus(422)->assertJsonValidationErrors(['title' => 'Maksimal 10 milestone per proyek.']);
        $this->assertSame(10, $this->project->milestones()->count());
    }

    // ── Mengubah ──

    public function test_milestone_planned_boleh_diubah_penuh(): void
    {
        $m = $this->milestone();
        $this->actingAs($this->dev);

        $this->patchJson("/api/milestones/{$m->id}", ['title' => 'Desain UI', 'description' => 'Baru', 'due_date' => now()->addDays(10)->toDateString()])
            ->assertOk()->assertJsonPath('data.title', 'Desain UI')->assertJsonPath('data.description', 'Baru');

        $log = ProjectActivity::where('type', 'milestone.updated')->sole();
        $this->assertSame($this->dev->id, $log->actor_id);
        $this->assertSame('Desain UI', $log->meta['title']);

        // tanpa perubahan: tidak mencatat lagi
        $this->patchJson("/api/milestones/{$m->id}", ['title' => 'Desain UI'])->assertOk();
        $this->assertSame(1, ProjectActivity::where('type', 'milestone.updated')->count());
    }

    public function test_milestone_yang_sudah_dikerjakan_hanya_tenggat_yang_boleh_diubah(): void
    {
        $m = $this->milestone('Desain', MilestoneStatus::InProgress, 30);
        $this->actingAs($this->dev);

        $this->patchJson("/api/milestones/{$m->id}", ['title' => 'Nama Baru'])
            ->assertStatus(409)->assertJsonPath('message', 'Judul dan deskripsi tidak dapat diubah setelah milestone dikerjakan.');
        $this->patchJson("/api/milestones/{$m->id}", ['description' => 'Baru'])->assertStatus(409);

        $this->patchJson("/api/milestones/{$m->id}", ['title' => 'Desain', 'due_date' => '2030-01-01'])->assertOk()->assertJsonPath('data.due_date', '2030-01-01');
        $this->assertSame('Desain', $m->fresh()->title);
    }

    public function test_milestone_planned_dengan_update_dianggap_dikerjakan(): void
    {
        $m = $this->milestone();
        $update = new ProjectUpdate;
        $update->project_id = $this->project->id;
        $update->milestone_id = $m->id;
        $update->user_id = $this->dev->id;
        $update->type = 'progress';
        $update->note = 'catatan lama';
        $update->percent = 0;
        $update->links = [];
        $update->save();

        $this->actingAs($this->dev)->patchJson("/api/milestones/{$m->id}", ['title' => 'Nama Baru'])->assertStatus(409);
    }

    // ── Menghapus ──

    public function test_hapus_milestone_planned_merapatkan_urutan(): void
    {
        $a = $this->milestone('A');
        $b = $this->milestone('B');
        $c = $this->milestone('C');
        $this->actingAs($this->dev);

        $this->deleteJson("/api/milestones/{$b->id}")->assertOk();

        $this->assertSoftDeleted($b);
        $this->assertSame(1, $a->fresh()->position);
        $this->assertSame(2, $c->fresh()->position);
        $log = ProjectActivity::where('type', 'milestone.deleted')->sole();
        $this->assertSame(['milestone_id' => $b->id, 'title' => 'B'], $log->meta);
        $this->deleteJson("/api/milestones/{$b->id}")->assertNotFound(); // sudah terhapus
    }

    public function test_milestone_yang_dikerjakan_tidak_bisa_dihapus(): void
    {
        $this->actingAs($this->dev);

        foreach ([MilestoneStatus::InProgress, MilestoneStatus::Submitted, MilestoneStatus::Revision, MilestoneStatus::Approved] as $status) {
            $m = $this->milestone("M {$status->value}", $status, 50);
            $this->deleteJson("/api/milestones/{$m->id}")->assertStatus(409)->assertJsonPath('message', 'Milestone yang sudah dikerjakan tidak dapat dihapus.');
            $this->assertNotSoftDeleted($m);
        }
    }

    public function test_izin_ubah_dan_hapus(): void
    {
        $m = $this->milestone();

        foreach ([$this->project->user, $this->staff(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->patchJson("/api/milestones/{$m->id}", ['title' => 'Hack'])->assertForbidden();
            $this->actingAs($user)->deleteJson("/api/milestones/{$m->id}")->assertForbidden();
        }
        $this->assertSame('Desain', $m->fresh()->title);
        $this->assertNotSoftDeleted($m);
    }

    // ── Mengurutkan ──

    public function test_urutkan_milestone_planned_dan_tetapkan_yang_dikerjakan(): void
    {
        $a = $this->milestone('A');
        $b = $this->milestone('B', MilestoneStatus::InProgress, 20); // tetap di posisi 2
        $c = $this->milestone('C');
        $d = $this->milestone('D');
        $this->actingAs($this->dev);

        $this->patchJson("/api/projects/{$this->project->id}/milestones/order", ['ids' => [$d->id, $c->id, $a->id]])->assertOk()
            ->assertJsonPath('data.0.id', $d->id)->assertJsonPath('data.1.id', $b->id)->assertJsonPath('data.2.id', $c->id)->assertJsonPath('data.3.id', $a->id);

        $this->assertSame([1, 2, 3, 4], [$d->fresh()->position, $b->fresh()->position, $c->fresh()->position, $a->fresh()->position]);
        $this->assertSame(1, ProjectActivity::where('type', 'milestone.reordered')->count());

        // urutan sama: tidak mencatat lagi
        $this->patchJson("/api/projects/{$this->project->id}/milestones/order", ['ids' => [$d->id, $c->id, $a->id]])->assertOk();
        $this->assertSame(1, ProjectActivity::where('type', 'milestone.reordered')->count());
    }

    public function test_urutan_harus_memuat_semua_milestone_planned(): void
    {
        $a = $this->milestone('A');
        $b = $this->milestone('B');
        $worked = $this->milestone('C', MilestoneStatus::InProgress, 10);
        $this->actingAs($this->dev);
        $url = "/api/projects/{$this->project->id}/milestones/order";

        $this->patchJson($url, ['ids' => [$a->id]])->assertStatus(422);
        $this->patchJson($url, ['ids' => [$a->id, $b->id, $worked->id]])->assertStatus(422)
            ->assertJsonValidationErrors(['ids' => 'Daftar urutan harus memuat semua milestone yang masih direncanakan.']);
        $this->patchJson($url, ['ids' => [$a->id, $a->id]])->assertStatus(422);
        $this->patchJson($url, [])->assertStatus(422);
        $this->actingAs($this->project->user)->patchJson($url, ['ids' => [$b->id, $a->id]])->assertForbidden();
    }

    // ── Membaca ──

    public function test_peserta_membaca_daftar_milestone_berurutan(): void
    {
        $this->milestone('A');
        $this->milestone('B', MilestoneStatus::Submitted, 100);
        $url = "/api/projects/{$this->project->id}/milestones";

        foreach ([$this->project->user, $this->dev] as $viewer) {
            $res = $this->actingAs($viewer)->getJson($url)->assertOk()->assertJsonCount(2, 'data');
            $this->assertSame(['A', 'B'], array_column($res->json('data'), 'title'));
            $this->assertSame('Menunggu review', $res->json('data.1.status_label'));
        }

        auth()->logout();
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $this->actingAs($this->staff())->getJson($url)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->getJson($url)->assertForbidden();
        $this->actingAs($this->project->user)->getJson('/api/projects/99999/milestones')->assertNotFound();
    }

    public function test_milestone_terkunci_sebelum_pembayaran_untuk_pembaca(): void
    {
        $unpaid = $this->workspace($this->dev, paid: false);

        $this->actingAs($unpaid->user)->getJson("/api/projects/{$unpaid->id}/milestones")->assertStatus(409);
    }

    // ── Persentase proyek & realtime ──

    public function test_persentase_proyek_adalah_rata_rata_milestone(): void
    {
        $calc = app(ProgressCalculator::class);
        $this->assertSame(0, $calc->calculate($this->project), 'tanpa milestone');

        $this->milestone('A'); // planned = 0
        $this->milestone('B', MilestoneStatus::InProgress, 40);
        $this->milestone('C', MilestoneStatus::Approved, 100);
        $this->milestone('D', MilestoneStatus::Submitted, 100);
        $this->assertSame(60, $calc->calculate($this->project)); // (0+40+100+100)/4 = 60

        $this->milestone('E', MilestoneStatus::Revision, 90);
        $this->assertSame(66, $calc->calculate($this->project->refresh())); // 330/5 = 66
    }

    public function test_menambah_dan_menghapus_milestone_menghitung_ulang_persentase(): void
    {
        $this->milestone('A', MilestoneStatus::Approved, 100);
        $this->project->update(['progress_percent' => 0]);
        $this->project->forceFill(['progress_percent' => 100])->save();
        $this->actingAs($this->dev);

        $id = $this->createMilestone(['title' => 'Tahap baru'])->assertCreated()->json('data.id');
        $this->assertSame(50, $this->project->fresh()->progress_percent); // (100+0)/2

        $this->deleteJson("/api/milestones/{$id}")->assertOk();
        $this->assertSame(100, $this->project->fresh()->progress_percent);
    }

    public function test_perubahan_milestone_disiarkan_dengan_daftar_terbaru(): void
    {
        Event::fake([MilestoneChanged::class]);
        $this->actingAs($this->dev);

        $this->createMilestone(['title' => 'Desain'])->assertCreated();

        Event::assertDispatched(MilestoneChanged::class, function ($e) {
            $payload = $e->broadcastWith();

            return array_map(fn ($c) => $c->name, $e->broadcastOn()) === ["private-project.{$this->project->id}"]
                && $e->broadcastAs() === 'milestone.changed'
                && count($payload['milestones']) === 1
                && $payload['milestones'][0]['title'] === 'Desain'
                && $payload['project'] === ['status' => 'approved', 'progress_percent' => 0];
        });
    }
}
