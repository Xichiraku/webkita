<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserAdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_user_hanya_staf_dan_tanpa_data_rahasia(): void
    {
        User::factory()->count(2)->create();
        $dev = User::factory()->create();
        $dev->role = Role::Developer;
        $dev->save();

        $this->getJson('/api/users')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/users')->assertForbidden();

        $res = $this->actingAs($dev)->getJson('/api/users')->assertOk();
        $this->assertCount(4, $res->json('data'));
        $this->assertArrayNotHasKey('password', $res->json('data.0'));
        $this->assertArrayNotHasKey('verification_token', $res->json('data.0'));
        $this->assertArrayNotHasKey('reset_otp', $res->json('data.0'));
        $this->assertSame('user', collect($res->json('data'))->firstWhere('id', 1)['role']);
    }

    public function test_hapus_user_hanya_admin_dan_soft_delete(): void
    {
        $target = User::factory()->create(['username' => 'target']);
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
        $dev = User::factory()->create();
        $dev->role = Role::Developer;
        $dev->save();

        $this->actingAs($dev)->deleteJson('/api/users/target')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->deleteJson('/api/users/target')->assertOk();

        $this->assertSoftDeleted($target);
        $this->assertDatabaseMissing('sessions', ['id' => 's1']);
    }

    public function test_hapus_user_tidak_ditemukan_atau_diri_sendiri(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'bos']);
        $this->actingAs($admin);

        $this->deleteJson('/api/users/tidakada')->assertNotFound();
        $this->deleteJson('/api/users/bos')->assertStatus(409);
        $this->assertNotSoftDeleted($admin);
    }
}
