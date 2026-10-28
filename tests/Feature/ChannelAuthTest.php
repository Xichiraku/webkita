<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Otorisasi channel privat Reverb. Driver diganti sebelum aplikasi boot supaya
 * routes/channels.php terdaftar pada broadcaster yang benar-benar memeriksa channel.
 */
class ChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    private const ENV = [
        'BROADCAST_CONNECTION' => 'reverb', 'REVERB_APP_ID' => 'id', 'REVERB_APP_KEY' => 'key', 'REVERB_APP_SECRET' => 'secret',
    ];

    private array $previous = [];

    protected function setUp(): void
    {
        // Harus sebelum aplikasi dibuat: channels.php didaftarkan ke driver default saat boot.
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

    private function auth(?User $user, string $channel)
    {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '123.456']);
    }

    public function test_channel_user_hanya_untuk_pemilik(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->auth($owner, "private-App.Models.User.{$owner->id}")->assertOk()->assertJsonStructure(['auth']);
        $this->auth($other, "private-App.Models.User.{$owner->id}")->assertForbidden();
    }

    public function test_channel_admin_hanya_untuk_staf(): void
    {
        $this->auth(User::factory()->create(), 'private-admin.orders')->assertForbidden();
        $this->auth(User::factory()->admin()->create(), 'private-admin.orders')->assertOk();
    }

    public function test_tamu_ditolak(): void
    {
        $this->auth(null, 'private-admin.orders')->assertForbidden();
    }
}
