<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_keamanan_ada_di_semua_halaman_web(): void
    {
        foreach (['/', '/login', '/harga'] as $url) {
            $this->get($url)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeaderMissing('Strict-Transport-Security');
        }

        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_cookie_sesi_httponly_dan_samesite(): void
    {
        $cookie = collect($this->get('/login')->headers->getCookies())->first(fn ($c) => str_contains($c->getName(), 'session'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_api_dibatasi_per_menit(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/pricing/public')->assertOk();
        }

        $this->getJson('/api/pricing/public')->assertStatus(429);
    }

    public function test_unggah_lampiran_dibatasi_lebih_ketat(): void
    {
        Storage::fake('local');
        $project = Project::factory()->create();
        $this->actingAs($project->user);

        for ($i = 0; $i < 20; $i++) {
            $this->postJson("/api/attachments/{$project->id}", ['file' => UploadedFile::fake()->create("f{$i}.pdf", 1, 'application/pdf')])->assertCreated();
        }

        $this->postJson("/api/attachments/{$project->id}", ['file' => UploadedFile::fake()->create('lagi.pdf', 1, 'application/pdf')])->assertStatus(429);
    }

    public function test_ganti_password_mengeluarkan_sesi_di_perangkat_lain(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'perangkat-lain', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'milik-orang-lain', 'user_id' => User::factory()->create()->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password', 'password' => 'PasswordBaru1', 'password_confirmation' => 'PasswordBaru1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'perangkat-lain']);
        $this->assertDatabaseHas('sessions', ['id' => 'milik-orang-lain']);
    }

    public function test_login_gagal_tidak_membedakan_user_ada_atau_tidak(): void
    {
        User::factory()->create(['username' => 'ada']);

        $a = $this->post('/login', ['login' => 'ada', 'password' => 'salah123'])->assertSessionHasErrors('login');
        $b = $this->post('/login', ['login' => 'tidakada', 'password' => 'salah123'])->assertSessionHasErrors('login');

        $this->assertSame(session('errors')->first('login'), 'Email/username atau password salah');
        $this->assertNotNull($a);
        $this->assertNotNull($b);
    }

    /** Sapuan IDOR: pengguna biasa tidak boleh menyentuh data orang lain lewat endpoint mana pun. */
    public function test_pengguna_tidak_bisa_mengakses_data_milik_orang_lain(): void
    {
        Storage::fake('local');
        $victim = User::factory()->create();
        $project = Project::factory()->for($victim)->create(['plan_price_range' => '3000000']);
        $payment = Payment::factory()->for($project)->create();
        Storage::disk('local')->put('attachments/x.pdf', 'rahasia');
        $att = ProjectAttachment::create(['project_id' => $project->id, 'file_name' => 'x.pdf', 'file_size' => 7, 'file_type' => 'application/pdf', 'storage_path' => 'attachments/x.pdf']);

        $this->actingAs(User::factory()->create());

        $blocked = [
            ['GET', "/api/projects/{$project->id}"],
            ['PATCH', "/api/projects/{$project->id}/status", ['status' => 'approved']],
            ['PATCH', "/api/projects/{$project->id}/price", ['plan_price_range' => 1]],
            ['DELETE', "/api/projects/{$project->id}"],
            ['GET', "/api/projects/user/{$victim->id}"],
            ['GET', '/api/projects'],
            ['GET', "/api/attachments/{$project->id}"],
            ['POST', "/api/attachments/{$project->id}", ['file' => UploadedFile::fake()->create('a.pdf', 1, 'application/pdf')]],
            ['GET', "/api/attachments/download/{$att->id}"],
            ['POST', "/api/payments/create/{$project->id}"],
            ['GET', "/api/payments/status/{$payment->order_id}"],
            ['GET', "/api/payments/project/{$project->id}"],
            ['GET', "/api/payments/invoice/{$project->id}"],
            ['GET', '/api/payments'],
            ['GET', '/api/users'],
            ['DELETE', "/api/users/{$victim->username}"],
            ['GET', '/api/pricing'],
            ['POST', '/api/pricing', ['title' => 'x']],
            ['PATCH', "/api/projects/{$project->id}/developer", ['developer_id' => 1]],
            ['GET', "/orderan/{$project->id}"],
            ['GET', "/developer/proyek/{$project->id}"],
            ['GET', '/developer/proyek'],
            ['GET', "/invoice/{$project->id}"],
            ['GET', '/admin'],
        ];

        foreach ($blocked as $item) {
            [$method, $url] = $item;
            $res = $this->json($method, $url, $item[2] ?? []);
            $this->assertContains($res->getStatusCode(), [403, 404], "{$method} {$url} harus ditolak, dapat {$res->getStatusCode()}");
        }

        $this->assertNotSoftDeleted($project);
        $this->assertNotSoftDeleted($victim);
    }
}
