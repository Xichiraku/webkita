<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Events\ProjectCommentDeleted;
use App\Events\ProjectCommentPosted;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\ProjectMilestone;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    private Project $project;

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
    }

    private function url(?Project $project = null): string
    {
        return '/api/projects/'.($project ?? $this->project)->id.'/comments';
    }

    private function update(?Project $project = null): ProjectUpdate
    {
        $project ??= $this->project;
        $milestone = new ProjectMilestone(['title' => 'M']);
        $milestone->project_id = $project->id;
        $milestone->position = 1;
        $milestone->save();

        $u = new ProjectUpdate;
        $u->project_id = $project->id;
        $u->milestone_id = $milestone->id;
        $u->user_id = $this->dev->id;
        $u->type = ProjectUpdate::TYPE_PROGRESS;
        $u->note = 'Catatan progres';
        $u->percent = 10;
        $u->links = [];
        $u->save();

        return $u;
    }

    private function comment(User $by, string $body = 'Halo', ?int $updateId = null, ?Project $project = null): ProjectComment
    {
        $c = new ProjectComment;
        $c->project_id = ($project ?? $this->project)->id;
        $c->user_id = $by->id;
        $c->body = $body;
        $c->update_id = $updateId;
        $c->save();

        return $c;
    }

    public function test_klien_dan_developer_dapat_berkomentar(): void
    {
        Event::fake([ProjectCommentPosted::class]);
        $update = $this->update();

        $this->actingAs($this->project->user)->postJson($this->url(), ['body' => "  Logo masih salah warna.\nTolong diperbaiki.  ", 'update_id' => $update->id])
            ->assertCreated()->assertJsonPath('data.body', "Logo masih salah warna.\nTolong diperbaiki.")->assertJsonPath('data.update_id', $update->id)
            ->assertJsonPath('data.deleted', false)->assertJsonPath('data.can_delete', true)->assertJsonPath('data.author.role', 'user');
        $this->actingAs($this->dev)->postJson($this->url(), ['body' => 'Siap, segera.'])->assertCreated()->assertJsonPath('data.update_id', null)->assertJsonPath('data.author.role', 'developer');

        $this->assertSame(2, ProjectComment::count());
        Event::assertDispatched(ProjectCommentPosted::class, 2);
    }

    public function test_html_tidak_dirender_dan_disimpan_apa_adanya(): void
    {
        $this->actingAs($this->dev)->postJson($this->url(), ['body' => '<script>alert(1)</script> <b>tebal</b>'])->assertCreated()
            ->assertJsonPath('data.body', '<script>alert(1)</script> <b>tebal</b>');
    }

    public function test_validasi_komentar(): void
    {
        $this->actingAs($this->dev);

        $this->postJson($this->url(), [])->assertStatus(422)->assertJsonValidationErrors(['body' => 'Komentar wajib diisi (1–2000 karakter).']);
        $this->postJson($this->url(), ['body' => "   \n  "])->assertStatus(422)->assertJsonValidationErrors('body');
        $this->postJson($this->url(), ['body' => str_repeat('x', 2001)])->assertStatus(422)->assertJsonValidationErrors('body');
        $this->postJson($this->url(), ['body' => str_repeat('x', 2000)])->assertCreated();
        $this->postJson($this->url(), ['body' => 'a', 'update_id' => 'x'])->assertStatus(422)->assertJsonValidationErrors('update_id');

        $other = Project::factory()->create();
        $foreign = $this->update($other);
        $this->postJson($this->url(), ['body' => 'a', 'update_id' => $foreign->id])->assertStatus(422)->assertJsonValidationErrors(['update_id' => 'Update tidak ditemukan pada proyek ini.']);
        $this->postJson($this->url(), ['body' => 'a', 'update_id' => 99999])->assertStatus(422);
        $this->assertSame(1, ProjectComment::count());
    }

    public function test_izin_komentar(): void
    {
        $this->postJson($this->url(), ['body' => 'a'])->assertUnauthorized();
        $this->getJson($this->url())->assertUnauthorized();

        foreach ([User::factory()->admin()->create(), User::factory()->create(), $this->otherStaff()] as $outsider) {
            $this->actingAs($outsider)->postJson($this->url(), ['body' => 'a'])->assertForbidden();
            $this->actingAs($outsider)->getJson($this->url())->assertForbidden();
        }

        $this->project->payments()->delete();
        $this->actingAs($this->dev)->postJson($this->url(), ['body' => 'a'])->assertStatus(409);
        $this->assertSame(0, ProjectComment::count());
    }

    private function otherStaff(): User
    {
        $u = User::factory()->create();
        $u->role = Role::Developer;
        $u->save();

        return $u;
    }

    public function test_komentar_tetap_diizinkan_setelah_proyek_selesai(): void
    {
        $this->project->forceFill(['status' => ProjectStatus::Done])->save();

        $this->actingAs($this->project->user)->postJson($this->url(), ['body' => 'Terima kasih!'])->assertCreated();
    }

    public function test_batas_laju_dua_puluh_komentar_per_menit(): void
    {
        $this->actingAs($this->dev);

        for ($i = 0; $i < 20; $i++) {
            $this->postJson($this->url(), ['body' => "k{$i}"])->assertCreated();
        }

        $this->postJson($this->url(), ['body' => 'lagi'])->assertStatus(429);
    }

    public function test_daftar_komentar_paginasi_dan_filter(): void
    {
        $update = $this->update();
        $this->comment($this->dev, 'satu');
        $this->comment($this->project->user, 'dua', $update->id);
        $this->comment($this->dev, 'tiga');
        $this->actingAs($this->project->user);

        $res = $this->getJson($this->url())->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame(['tiga', 'dua', 'satu'], array_column($res->json('data'), 'body'), 'terbaru dulu');
        $this->assertFalse($res->json('meta.has_more'));

        $this->getJson($this->url().'?limit=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.has_more', true);
        $this->getJson($this->url()."?update_id={$update->id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.body', 'dua');
        $id = $this->getJson($this->url().'?limit=1')->json('data.0.id');
        $this->getJson($this->url()."?before_id={$id}")->assertJsonCount(2, 'data');
        $this->actingAs($this->dev)->getJson($this->url())->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_komentar_proyek_lain_tidak_muncul(): void
    {
        $other = Project::factory()->create();
        $this->comment($other->user, 'rahasia', null, $other);

        $this->actingAs($this->dev)->getJson($this->url())->assertJsonCount(0, 'data');
    }

    // ── Hapus ──

    public function test_pemilik_menghapus_dalam_lima_belas_menit(): void
    {
        Event::fake([ProjectCommentDeleted::class]);
        $c = $this->comment($this->dev, 'rahasia');
        $this->actingAs($this->dev);

        $this->deleteJson("/api/comments/{$c->id}")->assertOk();

        $this->assertSoftDeleted($c);
        $this->assertSame($this->dev->id, $c->fresh()?->deleted_by ?? ProjectComment::withTrashed()->find($c->id)->deleted_by);
        Event::assertDispatched(ProjectCommentDeleted::class, fn ($e) => $e->broadcastWith() === ['id' => $c->id]);

        $res = $this->getJson($this->url())->assertOk()->assertJsonPath('data.0.deleted', true)->assertJsonPath('data.0.body', null)->assertJsonPath('data.0.can_delete', false);
        $this->assertStringNotContainsString('rahasia', $res->getContent());

        $this->deleteJson("/api/comments/{$c->id}")->assertStatus(404); // sudah soft delete: tidak ditemukan
    }

    public function test_hapus_setelah_lima_belas_menit_ditolak(): void
    {
        $c = $this->comment($this->dev);
        $this->actingAs($this->dev);

        $this->travel(14)->minutes();
        $this->getJson($this->url())->assertJsonPath('data.0.can_delete', true);

        $this->travel(2)->minutes();
        $this->getJson($this->url())->assertJsonPath('data.0.can_delete', false);
        $this->deleteJson("/api/comments/{$c->id}")->assertForbidden()->assertJsonPath('message', 'Waktu menghapus komentar sudah lewat.');
        $this->assertNotSoftDeleted($c);
    }

    public function test_komentar_milik_orang_lain_tidak_dapat_dihapus(): void
    {
        $c = $this->comment($this->project->user);

        $this->actingAs($this->dev)->deleteJson("/api/comments/{$c->id}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->deleteJson("/api/comments/{$c->id}")->assertForbidden();
        $this->actingAs(User::factory()->create())->deleteJson("/api/comments/{$c->id}")->assertForbidden();
        $this->assertNotSoftDeleted($c);

        auth()->logout();
        $this->deleteJson("/api/comments/{$c->id}")->assertUnauthorized();
    }
}
