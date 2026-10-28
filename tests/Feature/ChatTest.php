<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Events\ChatMessageDeleted;
use App\Events\ChatMessageSent;
use App\Events\ChatUnreadChanged;
use App\Models\ChatMessage;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectAttachment;
use App\Models\User;
use App\Services\ProjectActivityLogger;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $dev;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->dev = $this->staff();
        $this->project = $this->workspace($this->dev);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->role = Role::Developer;
        $u->save();

        return $u;
    }

    private function workspace(?User $dev, ProjectStatus $status = ProjectStatus::Process, bool $paid = true): Project
    {
        $p = Project::factory()->create(['plan_price_range' => '3000000']);
        $p->status = $status;
        $p->developer_id = $dev?->id;
        $p->save();

        if ($paid) {
            Payment::factory()->for($p)->paid()->create(['amount' => 3000000]);
        }

        return $p;
    }

    private function url(string $suffix = '', ?Project $project = null): string
    {
        return '/api/projects/'.($project ?? $this->project)->id.'/messages'.$suffix;
    }

    private function msg(User $by, ?string $body = 'Halo', ?Project $project = null): ChatMessage
    {
        $m = new ChatMessage;
        $m->project_id = ($project ?? $this->project)->id;
        $m->user_id = $by->id;
        $m->body = $body;
        $m->save();

        return $m;
    }

    private function client(): User
    {
        return $this->project->user;
    }

    // ── Kirim ──

    public function test_klien_dan_developer_saling_berkirim_pesan(): void
    {
        Event::fake([ChatMessageSent::class, ChatUnreadChanged::class]);

        $this->actingAs($this->client())->postJson($this->url(), ['body' => "  Halo, apakah logo sudah final?\n  "])->assertCreated()
            ->assertJsonPath('data.body', 'Halo, apakah logo sudah final?')->assertJsonPath('data.author.role', 'user')
            ->assertJsonPath('data.can_delete', true)->assertJsonPath('data.attachments', []);
        $this->actingAs($this->dev)->postJson($this->url(), ['body' => 'Sudah, silakan dicek.'])->assertCreated()->assertJsonPath('data.author.role', 'developer');

        $this->assertSame(2, ChatMessage::count());
        Event::assertDispatched(ChatMessageSent::class, fn ($e) => $e->broadcastAs() === 'message.created'
            && array_map(fn ($c) => $c->name, $e->broadcastOn()) === ["private-project.{$this->project->id}"]
            && $e->broadcastWith()['can_delete'] === false);
        Event::assertDispatched(ChatUnreadChanged::class, fn ($e) => $e->userId === $this->dev->id && $e->unread === 1);
        Event::assertDispatched(ChatUnreadChanged::class, fn ($e) => $e->userId === $this->client()->id && $e->unread === 1);
    }

    public function test_html_disimpan_apa_adanya_sebagai_teks(): void
    {
        $this->actingAs($this->dev)->postJson($this->url(), ['body' => '<img src=x onerror=alert(1)> javascript:alert(1)'])->assertCreated()
            ->assertJsonPath('data.body', '<img src=x onerror=alert(1)> javascript:alert(1)');
    }

    public function test_validasi_pesan(): void
    {
        $this->actingAs($this->dev);

        $this->postJson($this->url(), [])->assertStatus(422)->assertJsonPath('message', 'Tulis pesan atau lampirkan berkas.');
        $this->postJson($this->url(), ['body' => "  \n "])->assertStatus(422)->assertJsonPath('message', 'Tulis pesan atau lampirkan berkas.');
        $this->postJson($this->url(), ['body' => str_repeat('x', 2001)])->assertStatus(422)->assertJsonValidationErrors(['body' => 'Pesan maksimal 2000 karakter.']);
        $this->postJson($this->url(), ['body' => str_repeat('x', 2000)])->assertCreated();
        $this->assertSame(1, ChatMessage::count());
    }

    public function test_pesan_hanya_lampiran_diizinkan_dan_disimpan_privat(): void
    {
        $this->actingAs($this->client());

        $res = $this->post($this->url(), ['files' => [UploadedFile::fake()->image('logo.png', 40, 40), UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf')]], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.body', null)->assertJsonCount(2, 'data.attachments')->assertJsonPath('data.attachments.0.file_name', 'logo.png');

        $attachment = ProjectAttachment::where('file_name', 'logo.png')->sole();
        $this->assertSame($res->json('data.id'), $attachment->message_id);
        $this->assertNull($attachment->update_id);
        $this->assertSame($this->client()->id, $attachment->uploaded_by);
        $this->assertStringStartsWith("attachments/{$this->project->id}/chat/{$attachment->message_id}/", $attachment->storage_path);
        Storage::disk('local')->assertExists($attachment->storage_path);
    }

    public function test_validasi_lampiran(): void
    {
        $this->actingAs($this->dev);
        $send = fn (array $files) => $this->post($this->url(), ['body' => 'a', 'files' => $files], ['Accept' => 'application/json']);
        $message = 'Maksimal 3 lampiran, masing-masing 10 MB (PNG, JPG, atau PDF).';

        $send([UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])->assertStatus(422)->assertJsonValidationErrors(['files.0' => $message]);
        $send([UploadedFile::fake()->create('doc.zip', 10, 'application/zip')])->assertStatus(422);
        $send([UploadedFile::fake()->create('besar.pdf', 10 * 1024 + 1, 'application/pdf')])->assertStatus(422)->assertJsonValidationErrors(['files.0' => $message]);
        $send(array_map(fn ($i) => UploadedFile::fake()->create("f{$i}.pdf", 10, 'application/pdf'), range(1, 4)))->assertStatus(422)->assertJsonValidationErrors(['files' => $message]);
        $this->assertSame(0, ChatMessage::count());
        $this->assertSame([], Storage::disk('local')->allFiles());

        $send(array_map(fn ($i) => UploadedFile::fake()->create("f{$i}.pdf", 10, 'application/pdf'), range(1, 3)))->assertCreated();
    }

    // ── Izin dan keadaan ──

    public function test_izin_chat(): void
    {
        $this->postJson($this->url(), ['body' => 'a'])->assertUnauthorized();
        $this->getJson($this->url())->assertUnauthorized();

        $oldDev = $this->staff();
        foreach ([User::factory()->admin()->create(), User::factory()->create(), $oldDev] as $outsider) {
            $this->actingAs($outsider)->postJson($this->url(), ['body' => 'a'])->assertForbidden()->assertJsonPath('message', 'Anda bukan peserta percakapan ini.');
            $this->actingAs($outsider)->getJson($this->url())->assertForbidden();
            $this->actingAs($outsider)->postJson($this->url('/read'), ['last_message_id' => 1])->assertForbidden();
        }

        $this->assertSame(0, ChatMessage::count());
    }

    public function test_developer_yang_dilepas_kehilangan_akses_dan_riwayat_tetap_terbaca_pengganti(): void
    {
        $this->msg($this->dev, 'dari developer lama');
        $new = $this->staff();
        $this->project->forceFill(['developer_id' => $new->id])->save();

        $this->actingAs($this->dev)->getJson($this->url())->assertForbidden();
        $this->actingAs($this->dev)->postJson($this->url(), ['body' => 'a'])->assertForbidden();

        $res = $this->actingAs($new)->getJson($this->url())->assertOk()->assertJsonPath('meta.last_read_message_id', 0);
        $this->assertSame($this->dev->name, $res->json('data.0.author.name'));
        $this->actingAs($this->client())->getJson($this->url())->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_pembayaran_dan_developer_diperlukan(): void
    {
        $unpaid = $this->workspace($this->dev, paid: false);
        $noDev = $this->workspace(null);

        $this->actingAs($this->dev)->postJson($this->url('', $unpaid), ['body' => 'a'])->assertStatus(409)->assertJsonPath('message', 'Workspace aktif setelah pembayaran terkonfirmasi.');
        $this->actingAs($noDev->user)->postJson($this->url('', $noDev), ['body' => 'a'])->assertStatus(409)->assertJsonPath('message', 'Chat tersedia setelah developer ditugaskan.');
        $this->actingAs($noDev->user)->getJson($this->url('', $noDev))->assertStatus(409);
        $this->assertSame(0, ChatMessage::count());
    }

    public function test_status_proyek_menentukan_bisa_kirim(): void
    {
        $this->actingAs($this->dev);

        foreach ([ProjectStatus::Approved, ProjectStatus::Process] as $ok) {
            $this->project->forceFill(['status' => $ok])->save();
            $this->postJson($this->url(), ['body' => "status {$ok->value}"])->assertCreated();
        }

        $this->project->forceFill(['status' => ProjectStatus::Done])->save();
        $this->postJson($this->url(), ['body' => 'terlambat'])->assertStatus(409)->assertJsonPath('message', 'Chat sudah ditutup. Anda masih dapat membaca riwayat.');
        $this->getJson($this->url())->assertOk()->assertJsonCount(2, 'data'); // hanya-baca
        $this->assertSame(2, ChatMessage::count());
    }

    public function test_batas_laju_tiga_puluh_pesan_per_menit(): void
    {
        $this->actingAs($this->dev);

        for ($i = 0; $i < 30; $i++) {
            $this->postJson($this->url(), ['body' => "p{$i}"])->assertCreated();
        }

        $this->postJson($this->url(), ['body' => 'lagi'])->assertStatus(429)->assertJsonPath('message', 'Terlalu banyak pesan. Coba lagi sebentar lagi.');
    }

    public function test_hanya_pesan_pertama_dicatat_ke_log(): void
    {
        $this->actingAs($this->client())->postJson($this->url(), ['body' => 'satu'])->assertCreated();
        $this->actingAs($this->dev)->postJson($this->url(), ['body' => 'dua'])->assertCreated();

        $log = ProjectActivity::where('type', 'chat.started')->sole();
        $this->assertSame($this->client()->id, $log->actor_id);
    }

    public function test_kegagalan_log_membatalkan_pesan_dan_berkas(): void
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
            $this->post($this->url(), ['body' => 'a', 'files' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json']);
            $this->fail('Seharusnya melempar exception');
        } catch (RuntimeException) {
        }

        $this->assertSame(0, ChatMessage::count());
        $this->assertSame(0, ProjectAttachment::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ── Daftar ──

    public function test_daftar_pesan_paginasi_dan_pemulihan_koneksi(): void
    {
        $ids = [];
        foreach (range(1, 5) as $i) {
            $ids[] = $this->msg($i % 2 ? $this->dev : $this->client(), "pesan {$i}")->id;
        }
        $other = $this->workspace($this->staff());
        $this->msg($other->user, 'proyek lain', $other);
        $this->actingAs($this->client());

        $res = $this->getJson($this->url())->assertOk()->assertJsonCount(5, 'data');
        $this->assertSame(array_reverse($ids), array_column($res->json('data'), 'id'), 'terbaru dulu');
        $this->assertFalse($res->json('meta.has_more'));

        $this->getJson($this->url('?limit=2'))->assertJsonCount(2, 'data')->assertJsonPath('meta.has_more', true);
        $this->getJson($this->url("?before_id={$ids[2]}"))->assertJsonCount(2, 'data');

        $missed = $this->getJson($this->url("?after_id={$ids[2]}"))->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame([$ids[3], $ids[4]], array_column($missed->json('data'), 'id'), 'naik');
    }

    // ── Hapus ──

    public function test_pengirim_menghapus_dalam_lima_menit(): void
    {
        Event::fake([ChatMessageDeleted::class, ChatUnreadChanged::class]);
        $m = $this->msg($this->dev, 'rahasia');
        $attachment = new ProjectAttachment;
        $attachment->project_id = $this->project->id;
        $attachment->message_id = $m->id;
        $attachment->file_name = 'a.pdf';
        $attachment->file_size = 10;
        $attachment->file_type = 'application/pdf';
        $attachment->storage_path = "attachments/{$this->project->id}/chat/{$m->id}/a.pdf";
        $attachment->save();
        Storage::disk('local')->put($attachment->storage_path, 'pdf');
        $this->actingAs($this->dev);

        $this->deleteJson("/api/messages/{$m->id}")->assertOk();

        $this->assertSoftDeleted($m);
        Event::assertDispatched(ChatMessageDeleted::class, fn ($e) => $e->broadcastWith() === ['id' => $m->id]);

        $res = $this->getJson($this->url())->assertJsonPath('data.0.deleted', true)->assertJsonPath('data.0.body', null)->assertJsonPath('data.0.attachments', []);
        $this->assertStringNotContainsString('rahasia', $res->getContent());
        $this->actingAs($this->client())->get("/api/attachments/download/{$attachment->id}")->assertNotFound();
        $this->actingAs($this->dev)->deleteJson("/api/messages/{$m->id}")->assertNotFound();
    }

    public function test_hapus_setelah_lima_menit_atau_milik_orang_lain_ditolak(): void
    {
        $m = $this->msg($this->dev);
        $this->actingAs($this->client())->deleteJson("/api/messages/{$m->id}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->deleteJson("/api/messages/{$m->id}")->assertForbidden();

        $this->actingAs($this->dev);
        $this->travel(4)->minutes();
        $this->getJson($this->url())->assertJsonPath('data.0.can_delete', true);
        $this->travel(2)->minutes();
        $this->getJson($this->url())->assertJsonPath('data.0.can_delete', false);
        $this->deleteJson("/api/messages/{$m->id}")->assertForbidden()->assertJsonPath('message', 'Waktu menghapus pesan sudah lewat.');
        $this->assertNotSoftDeleted($m);
    }

    // ── Unduh lampiran ──

    public function test_lampiran_chat_hanya_diunduh_peserta(): void
    {
        $this->actingAs($this->dev)->post($this->url(), ['files' => [UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])->assertCreated();
        $id = ProjectAttachment::sole()->id;

        foreach ([$this->client(), $this->dev] as $viewer) {
            $this->actingAs($viewer)->get("/api/attachments/download/{$id}")->assertOk();
        }

        foreach ([User::factory()->admin()->create(), User::factory()->create(), $this->staff()] as $outsider) {
            $this->actingAs($outsider)->get("/api/attachments/download/{$id}")->assertForbidden();
        }

        $this->actingAs($this->client())->getJson("/api/attachments/{$this->project->id}")->assertJsonCount(0, 'data');
    }

    // ── Belum dibaca ──

    public function test_belum_dibaca_per_peserta_dan_hanya_naik(): void
    {
        Event::fake([ChatUnreadChanged::class]);
        $a = $this->msg($this->client(), 'a')->id;
        $b = $this->msg($this->dev, 'b')->id;
        $c = $this->msg($this->client(), 'c')->id;

        $this->actingAs($this->dev)->getJson('/api/chat/unread')->assertOk()->assertJsonPath('data', [(string) $this->project->id => 2]);
        $this->actingAs($this->client())->getJson('/api/chat/unread')->assertJsonPath('data', [(string) $this->project->id => 1]);

        $this->actingAs($this->dev)->postJson($this->url('/read'), ['last_message_id' => $a])->assertOk()->assertJsonPath('data.unread', 1);
        $this->postJson($this->url('/read'), ['last_message_id' => $c])->assertOk()->assertJsonPath('data.unread', 0);
        $this->postJson($this->url('/read'), ['last_message_id' => $a])->assertOk()->assertJsonPath('data.unread', 0); // tidak turun
        $this->assertSame($c, (int) DB::table('chat_reads')->where('user_id', $this->dev->id)->value('last_read_message_id'));
        $this->getJson('/api/chat/unread')->assertJsonPath('data', []);

        $this->postJson($this->url('/read'), ['last_message_id' => 999999])->assertOk();
        $this->assertSame($c, (int) DB::table('chat_reads')->where('user_id', $this->dev->id)->value('last_read_message_id'), 'dibatasi id pesan terbesar');
        $this->postJson($this->url('/read'), [])->assertStatus(422);

        $this->actingAs($this->client())->getJson($this->url())->assertJsonPath('meta.last_read_message_id', 0);
        $this->getJson('/api/chat/unread')->assertJsonPath('data', [(string) $this->project->id => 1]); // pesan sendiri tidak dihitung
        Event::assertDispatched(ChatUnreadChanged::class, fn ($e) => $e->userId === $this->dev->id && $e->unread === 0);
    }

    public function test_pesan_terhapus_tidak_dihitung_belum_dibaca(): void
    {
        $m = $this->msg($this->client());
        $m->delete();

        $this->actingAs($this->dev)->getJson('/api/chat/unread')->assertJsonPath('data', []);
    }

    public function test_unread_tamu_ditolak(): void
    {
        $this->getJson('/api/chat/unread')->assertUnauthorized();
    }
}
