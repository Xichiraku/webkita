<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\ResetOtpMail;
use App\Mail\VerifyEmailMail;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function registerPayload(array $override = []): array
    {
        return array_merge([
            'username' => 'budi.s',
            'first_name' => 'Budi',
            'last_name' => 'Santoso',
            'email' => 'budi@example.test',
            'phone' => '081234567890',
            'password' => 'Rahasia123',
            'password_confirmation' => 'Rahasia123',
        ], $override);
    }

    public function test_register_membuat_akun_nonaktif_dan_mengirim_email_verifikasi(): void
    {
        Mail::fake();

        $this->post('/register', $this->registerPayload())
            ->assertRedirect(route('verification.notice', ['email' => 'budi@example.test']));

        $user = User::where('email', 'budi@example.test')->firstOrFail();
        $this->assertSame(Role::User, $user->role);
        $this->assertSame('N', $user->is_aktif);
        $this->assertNull($user->email_verified_at);
        $this->assertGuest();
        Mail::assertSent(VerifyEmailMail::class, fn ($m) => $m->hasTo('budi@example.test'));
    }

    public function test_register_tidak_bisa_menyetel_role_admin(): void
    {
        Mail::fake();

        $this->post('/register', $this->registerPayload(['role' => 'admin', 'is_aktif' => 'Y']));

        $user = User::where('email', 'budi@example.test')->firstOrFail();
        $this->assertSame(Role::User, $user->role);
        $this->assertSame('N', $user->is_aktif);
    }

    public function test_register_validasi(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'budi@example.test', 'username' => 'sudahada']);

        $this->post('/register', $this->registerPayload(['username' => 'a b']))->assertSessionHasErrors('username');
        $this->post('/register', $this->registerPayload(['username' => 'sudahada', 'email' => 'lain@example.test']))->assertSessionHasErrors('username');
        $this->post('/register', $this->registerPayload())->assertSessionHasErrors('email');
        $this->post('/register', $this->registerPayload(['email' => 'x@example.test', 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh']))->assertSessionHasErrors('password');
        $this->post('/register', $this->registerPayload(['email' => 'x@example.test', 'password_confirmation' => 'beda12345']))->assertSessionHasErrors('password');
    }

    public function test_alur_verifikasi_email_lalu_login(): void
    {
        Mail::fake();
        $this->post('/register', $this->registerPayload());

        $url = null;
        Mail::assertSent(VerifyEmailMail::class, function ($m) use (&$url) {
            $url = $m->verifyUrl;

            return true;
        });

        $this->post('/login', ['login' => 'budi.s', 'password' => 'Rahasia123'])
            ->assertSessionHasErrors(['login' => 'Email belum diverifikasi, silakan cek inbox Anda']);

        $this->get($url)->assertRedirect(route('login'));

        $user = User::where('email', 'budi@example.test')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('Y', $user->is_aktif);
        $this->assertNull($user->verification_token);

        $this->post('/login', ['login' => 'budi.s', 'password' => 'Rahasia123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        // tautan yang sama dipakai lagi -> sudah terverifikasi
        $this->post('/logout');
        $this->get($url)->assertRedirect(route('login'));
    }

    public function test_token_verifikasi_kadaluarsa_atau_salah_ditolak(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $token = $user->issueVerificationToken();

        $this->get(route('verification.verify', ['token' => 'salah', 'email' => $user->email]))
            ->assertRedirect(route('verification.notice'));
        $this->assertNull($user->fresh()->email_verified_at);

        $this->travel(25)->hours();
        $this->get(route('verification.verify', ['token' => $token, 'email' => $user->email]))
            ->assertSessionHasErrors('email');
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_kirim_ulang_verifikasi(): void
    {
        Mail::fake();
        $unverified = User::factory()->unverified()->create();
        $verified = User::factory()->create();

        $this->post('/email/resend', ['email' => $unverified->email])->assertSessionHas('status');
        Mail::assertSent(VerifyEmailMail::class, 1);

        $this->post('/email/resend', ['email' => $verified->email])->assertSessionHasErrors(['email' => 'Email sudah diverifikasi']);
        $this->post('/email/resend', ['email' => 'tidakada@example.test'])->assertSessionHasErrors(['email' => 'Email tidak ditemukan']);
    }

    public function test_login_dengan_email_dan_username(): void
    {
        $user = User::factory()->create(['username' => 'siti', 'email' => 'siti@example.test']);

        $this->post('/login', ['login' => 'siti@example.test', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->post('/login', ['login' => 'siti', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_gagal(): void
    {
        User::factory()->create(['username' => 'siti']);
        User::factory()->create(['username' => 'nonaktif', 'is_aktif' => 'N']);
        User::factory()->create(['username' => 'dihapus'])->delete();

        $this->post('/login', ['login' => 'siti', 'password' => 'salah123'])
            ->assertSessionHasErrors(['login' => 'Email/username atau password salah']);
        $this->post('/login', ['login' => 'tidakada', 'password' => 'password'])
            ->assertSessionHasErrors(['login' => 'Email/username atau password salah']);
        $this->post('/login', ['login' => 'nonaktif', 'password' => 'password'])
            ->assertSessionHasErrors(['login' => 'Akun tidak aktif']);
        $this->post('/login', ['login' => 'dihapus', 'password' => 'password'])
            ->assertSessionHasErrors(['login' => 'Email/username atau password salah']);
        $this->assertGuest();
    }

    public function test_login_dibatasi_setelah_5_percobaan_gagal(): void
    {
        User::factory()->create(['username' => 'siti']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'siti', 'password' => 'salah123']);
        }

        $this->post('/login', ['login' => 'siti', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_halaman_terproteksi_butuh_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_alur_lupa_password_dengan_otp(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'siti@example.test']);
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->post('/forgot-password', ['email' => 'siti@example.test'])
            ->assertRedirect(route('password.otp', ['email' => 'siti@example.test']));

        $otp = null;
        Mail::assertSent(ResetOtpMail::class, function ($m) use (&$otp) {
            $otp = $m->otp;

            return true;
        });
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
        $this->assertNotSame($otp, $user->fresh()->reset_otp, 'OTP tidak boleh disimpan plaintext');

        $response = $this->post('/verify-otp', ['email' => 'siti@example.test', 'otp' => $otp]);
        $response->assertRedirectContains('/reset-password/');
        $this->assertNull($user->fresh()->reset_otp);

        preg_match('#/reset-password/([^?]+)#', $response->headers->get('Location'), $m);
        $token = $m[1];

        $this->post('/reset-password', [
            'token' => $token, 'email' => 'siti@example.test',
            'password' => 'PasswordBaru1', 'password_confirmation' => 'PasswordBaru1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('PasswordBaru1', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'abc']);

        // token tidak bisa dipakai dua kali
        $this->post('/reset-password', [
            'token' => $token, 'email' => 'siti@example.test',
            'password' => 'LagiLagi123', 'password_confirmation' => 'LagiLagi123',
        ])->assertSessionHasErrors('email');
    }

    public function test_otp_salah_dihanguskan_setelah_5_kali(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'siti@example.test']);
        $this->post('/forgot-password', ['email' => 'siti@example.test']);

        $this->post('/verify-otp', ['email' => 'siti@example.test', 'otp' => '000000'])
            ->assertSessionHasErrors(['otp' => 'OTP tidak valid. Sisa percobaan: 4']);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/verify-otp', ['email' => 'siti@example.test', 'otp' => '000000']);
        }

        $this->assertNull($user->fresh()->reset_otp);
        $this->post('/verify-otp', ['email' => 'siti@example.test', 'otp' => '123456'])
            ->assertSessionHasErrors(['otp' => 'Kode OTP tidak ditemukan atau sudah dipakai. Minta kode baru.']);
    }

    public function test_otp_kadaluarsa(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'siti@example.test']);
        $this->post('/forgot-password', ['email' => 'siti@example.test']);
        $otp = null;
        Mail::assertSent(ResetOtpMail::class, function ($m) use (&$otp) {
            $otp = $m->otp;

            return true;
        });

        $this->travel(6)->minutes();
        $this->post('/verify-otp', ['email' => 'siti@example.test', 'otp' => $otp])
            ->assertSessionHasErrors(['otp' => 'OTP sudah kadaluarsa']);
    }

    public function test_forgot_password_email_tidak_ditemukan(): void
    {
        $this->post('/forgot-password', ['email' => 'tidakada@example.test'])
            ->assertSessionHasErrors(['email' => 'Email tidak ditemukan']);
    }

    public function test_update_profil_tidak_bisa_mengubah_role_atau_email(): void
    {
        $user = User::factory()->create(['email' => 'siti@example.test']);

        $this->actingAs($user)->patch('/profile', [
            'username' => 'siti.baru', 'first_name' => 'Siti', 'last_name' => 'Aminah',
            'phone' => '0811', 'role' => 'admin', 'email' => 'hack@example.test', 'subscribe_newsletter' => '1',
        ])->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('siti.baru', $user->username);
        $this->assertTrue($user->subscribe_newsletter);
        $this->assertSame(Role::User, $user->role);
        $this->assertSame('siti@example.test', $user->email);
    }

    public function test_ubah_password_butuh_password_lama(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/password', [
            'current_password' => 'salah', 'password' => 'Baru12345', 'password_confirmation' => 'Baru12345',
        ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->actingAs($user)->put('/password', [
            'current_password' => 'password', 'password' => 'Baru12345', 'password_confirmation' => 'Baru12345',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('Baru12345', $user->fresh()->password));
    }

    public function test_middleware_role(): void
    {
        Route::middleware(['web', 'auth', 'role:admin,developer'])->get('/_staff', fn () => 'ok');

        $this->get('/_staff')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/_staff')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/_staff')->assertOk();
        $dev = User::factory()->create();
        $dev->role = Role::Developer;
        $dev->save();
        $this->actingAs($dev)->get('/_staff')->assertOk();
    }

    public function test_policy_project_dan_payment(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $dev = User::factory()->create();
        $dev->role = Role::Developer;
        $dev->save();

        $project = Project::factory()->for($owner)->create();
        $payment = Payment::factory()->for($project)->create();

        $this->assertTrue($owner->can('view', $project));
        $this->assertFalse($other->can('view', $project));
        $this->assertTrue($dev->can('view', $project));
        $this->assertTrue($admin->can('view', $project));

        $this->assertFalse($owner->can('updateStatus', $project));
        $this->assertFalse($dev->can('updateStatus', $project));
        $this->assertTrue($admin->can('updateStatus', $project));
        $this->assertTrue($admin->can('delete', $project));
        $this->assertFalse($owner->can('delete', $project));

        $this->assertTrue($owner->can('view', $payment));
        $this->assertFalse($other->can('view', $payment));
        $this->assertTrue($dev->can('view', $payment));
        $this->assertTrue($admin->can('viewAny', Payment::class));
        $this->assertFalse($dev->can('viewAny', Payment::class));
        $this->assertTrue($owner->can('create', [Payment::class, $project]));
        $this->assertFalse($other->can('create', [Payment::class, $project]));
    }
}
