<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Otorisasi channel privat `project.{id}`: hanya klien pemilik dan developer yang ditugaskan,
 * dan hanya setelah pembayaran terkonfirmasi. Driver diganti sebelum aplikasi boot
 * (lihat ChannelAuthTest) agar channels.php terdaftar pada broadcaster yang memeriksa channel.
 */
class WorkspaceChannelTest extends TestCase
{
    use RefreshDatabase;

    private const ENV = [
        'BROADCAST_CONNECTION' => 'reverb', 'REVERB_APP_ID' => 'id', 'REVERB_APP_KEY' => 'key', 'REVERB_APP_SECRET' => 'secret',
    ];

    private array $previous = [];

    protected function setUp(): void
    {
        foreach (self::ENV as $k => $v) {
            $this->previous[$k] = [$_ENV[$k] ?? null, $_SERVER[$k] ?? null, getenv($k)];
            $_ENV[$k] = $_SERVER[$k] = $v;
            putenv("{$k}={$v}");
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->previous as $k => [$env, $server, $getenv]) {
            $env === null ? Arr::forget($_ENV, $k) : $_ENV[$k] = $env;
            $server === null ? Arr::forget($_SERVER, $k) : $_SERVER[$k] = $server;
            $getenv === false ? putenv($k) : putenv("{$k}={$getenv}");
        }
    }

    private function auth(?User $user, Project|int $project)
    {
        $id = $project instanceof Project ? $project->id : $project;
        $request = $user ? $this->actingAs($user) : $this;

        return $request->post('/broadcasting/auth', ['channel_name' => "private-project.{$id}", 'socket_id' => '123.456']);
    }

    private function project(bool $paid, ?User $developer = null): Project
    {
        $project = Project::factory()->create(['plan_price_range' => '3000000']);
        $project->status = ProjectStatus::Process;
        $project->developer_id = $developer?->id;
        $project->save();

        if ($paid) {
            Payment::factory()->for($project)->paid()->create(['amount' => 3000000]);
        }

        return $project;
    }

    private function developer(Role $role = Role::Developer): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    public function test_klien_dan_developer_ditugaskan_lolos_setelah_dibayar(): void
    {
        $dev = $this->developer();
        $project = $this->project(paid: true, developer: $dev);

        $this->auth($project->user, $project)->assertOk()->assertJsonStructure(['auth']);
        $this->auth($dev, $project)->assertOk();
    }

    public function test_admin_lolos_hanya_bila_ia_developer_proyek(): void
    {
        $admin = User::factory()->admin()->create();

        $this->auth($admin, $this->project(paid: true, developer: $this->developer()))->assertForbidden();
        $this->auth($admin, $this->project(paid: true))->assertForbidden();
        $this->auth($admin, $this->project(paid: true, developer: $admin))->assertOk();
    }

    public function test_ditolak_sebelum_pembayaran_terkonfirmasi(): void
    {
        $dev = $this->developer();
        $project = $this->project(paid: false, developer: $dev);

        $this->auth($project->user, $project)->assertForbidden();
        $this->auth($dev, $project)->assertForbidden();
    }

    public function test_pihak_lain_developer_lama_dan_tamu_ditolak(): void
    {
        $dev = $this->developer();
        $project = $this->project(paid: true, developer: $dev);

        $this->auth(User::factory()->create(), $project)->assertForbidden();
        $this->auth($this->developer(), $project)->assertForbidden();

        $project->forceFill(['developer_id' => $this->developer()->id])->save(); // developer diganti
        $this->auth($dev, $project)->assertForbidden();

        auth()->logout();
        $this->auth(null, $project)->assertForbidden();
    }

    public function test_proyek_tidak_ada_ditolak(): void
    {
        $this->auth(User::factory()->create(), 99999)->assertForbidden();
    }
}
