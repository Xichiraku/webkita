<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_upload_lampiran_oleh_pemilik(): void
    {
        $project = Project::factory()->create();

        $res = $this->actingAs($project->user)->postJson("/api/attachments/{$project->id}", [
            'file' => UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
        ])->assertCreated()->assertJsonPath('data.file_name', 'brief.pdf')->assertJsonPath('data.file_type', 'application/pdf');

        Storage::disk('local')->assertExists($res->json('data.storage_path'));
        $this->assertStringStartsWith("attachments/{$project->id}/", $res->json('data.storage_path'));
    }

    public function test_tipe_dan_ukuran_dibatasi(): void
    {
        $project = Project::factory()->create();
        $this->actingAs($project->user);
        $url = "/api/attachments/{$project->id}";

        $this->postJson($url, ['file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])
            ->assertStatus(422)->assertJsonValidationErrors(['file' => 'Tipe file harus PDF, PNG, atau JPG']);
        $this->postJson($url, ['file' => UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf')])
            ->assertStatus(422)->assertJsonValidationErrors(['file' => 'File maksimal 10MB']);
        $this->postJson($url, [])->assertStatus(422)->assertJsonValidationErrors(['file' => 'File tidak ditemukan']);
        $this->postJson($url, ['file' => UploadedFile::fake()->create('a.png', 10, 'image/png')])->assertCreated();
        $this->postJson($url, ['file' => UploadedFile::fake()->create('a.jpg', 10, 'image/jpeg')])->assertCreated();
    }

    public function test_user_lain_tidak_bisa_upload_lihat_atau_unduh(): void
    {
        $project = Project::factory()->create();
        $att = ProjectAttachment::create([
            'project_id' => $project->id, 'file_name' => 'x.pdf', 'file_size' => 3,
            'file_type' => 'application/pdf', 'storage_path' => 'attachments/x.pdf',
        ]);
        Storage::disk('local')->put('attachments/x.pdf', 'abc');
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->postJson("/api/attachments/{$project->id}", [
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
        $this->actingAs($intruder)->getJson("/api/attachments/{$project->id}")->assertForbidden();
        $this->actingAs($intruder)->get("/api/attachments/download/{$att->id}")->assertForbidden();
        $this->assertSame(0, ProjectAttachment::where('file_name', 'a.pdf')->count());
    }

    public function test_pemilik_dan_staf_bisa_melihat_dan_mengunduh(): void
    {
        $project = Project::factory()->create();
        Storage::disk('local')->put('attachments/x.pdf', 'isi-file');
        $att = ProjectAttachment::create([
            'project_id' => $project->id, 'file_name' => 'brief.pdf', 'file_size' => 8,
            'file_type' => 'application/pdf', 'storage_path' => 'attachments/x.pdf',
        ]);
        $dev = User::factory()->create();
        $dev->role = Role::Developer;
        $dev->save();

        foreach ([$project->user, $dev, User::factory()->admin()->create()] as $viewer) {
            $this->actingAs($viewer)->getJson("/api/attachments/{$project->id}")->assertOk()->assertJsonCount(1, 'data');
            $res = $this->actingAs($viewer)->get("/api/attachments/download/{$att->id}")->assertOk();
            $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
            $this->assertStringContainsString('brief.pdf', $res->headers->get('Content-Disposition'));
        }
    }

    public function test_unduh_file_tidak_ada(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/attachments/download/999')->assertNotFound();
    }
}
