<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Models\Payment;
use App\Models\PricingPlan;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_publik_dapat_diakses_tamu(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')->assertOk()->assertSee('Hubungkan Bisnis Anda dengan')->assertSee('Masuk')->assertSee('Daftar');
        $this->get('/carakerja')->assertOk()->assertSee('Cara Kerja Kami')->assertSee('Maintenance &amp; Support', false);
        $this->get('/harga')->assertOk()->assertSee('Paket Layanan Kami')->assertSee('Professional')->assertSee('Rp 5.000.000 – Rp 15.000.000', false);
    }

    public function test_halaman_harga_tanpa_paket(): void
    {
        $this->get('/harga')->assertOk()->assertSee('Belum ada paket layanan.');
    }

    public function test_navbar_menyesuaikan_status_login(): void
    {
        $user = User::factory()->create(['first_name' => 'Siti', 'last_name' => 'Aminah']);

        $this->get('/')->assertDontSee('Menu akun');
        $this->actingAs($user)->get('/')->assertSee('Menu akun')->assertSee('Siti Aminah')->assertSee('Orderan')->assertDontSee('Panel Admin');
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertSee('Panel Admin');
    }

    public function test_halaman_auth_memakai_desain_baru(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk ke Akun')->assertSee('Email atau Username');
        $this->get('/register')->assertOk()->assertSee('Buat Akun Baru')->assertSee('Konfirmasi Password');
        $this->get('/forgot-password')->assertOk()->assertSee('Lupa Password?');
        $this->get('/verify-otp?email=a@b.test')->assertOk()->assertSee('Masukkan Kode OTP')->assertSee('a@b.test');
        $this->get('/reset-password/token123?email=a@b.test')->assertOk()->assertSee('Password Baru');
        $this->get('/email/verify?email=a@b.test')->assertOk()->assertSee('Cek Email Anda');
    }

    public function test_halaman_klien_butuh_login(): void
    {
        $project = Project::factory()->create();

        foreach (['/orderan', '/orderan/baru', "/invoice/{$project->id}", '/profile', '/admin', '/admin/orderan'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_halaman_orderan_menampilkan_data_milik_sendiri(): void
    {
        $owner = User::factory()->create();
        Project::factory()->for($owner)->create(['project_title' => 'Toko Batik Saya']);
        Project::factory()->create(['project_title' => 'Rahasia Orang Lain']);

        $this->actingAs($owner)->get('/orderan')->assertOk()->assertSee('Orderan Saya')->assertSee('Toko Batik Saya')->assertDontSee('Rahasia Orang Lain');
    }

    public function test_formulir_pesan_dengan_dan_tanpa_paket(): void
    {
        $this->seed(DatabaseSeeder::class);
        $plan = PricingPlan::where('tier', 'starter')->first();
        $user = User::factory()->create();

        $this->actingAs($user)->get('/orderan/baru?plan='.$plan->id)->assertOk()->assertSee('Informasi Detail Proyek')->assertSee('Starter')->assertSee('Rp 500.000');
        $this->actingAs($user)->get('/orderan/baru?plan=tidak-ada')->assertOk()->assertSee('Belum memilih paket');
    }

    public function test_invoice_hanya_untuk_pemilik_atau_staf(): void
    {
        $project = Project::factory()->create(['plan_price_range' => '3000000']);
        $project->status = ProjectStatus::Approved;
        $project->forceFill(['invoice_number' => 'INV-0001-2026', 'invoice_issued_at' => now()])->save();

        $this->actingAs($project->user)->get("/invoice/{$project->id}")
            ->assertOk()->assertSee('INV-0001-2026')->assertSee('Rp 3.000.000')->assertSee('MENUNGGU PEMBAYARAN')->assertSee('Bayar sekarang');

        $this->actingAs(User::factory()->create())->get("/invoice/{$project->id}")->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get("/invoice/{$project->id}")->assertOk()->assertDontSee('Bayar sekarang');
    }

    public function test_invoice_lunas(): void
    {
        $project = Project::factory()->create(['plan_price_range' => '3000000']);
        $payment = Payment::factory()->for($project)->paid()->create(['amount' => 3000000, 'payment_type' => 'bank_transfer']);
        $this->assertSame(PaymentStatus::Paid, $payment->status);

        $this->actingAs($project->user)->get("/invoice/{$project->id}")->assertOk()->assertSee('LUNAS')->assertSee('Pembayaran Telah Diterima')->assertSee('bank transfer');
    }

    public function test_profil(): void
    {
        $user = User::factory()->create(['username' => 'siti', 'first_name' => 'Siti']);

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Profil Pengguna')->assertSee('@siti', false)->assertSee('Edit Profil');
    }

    public function test_dashboard_mengarahkan_sesuai_peran(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirect('/orderan');
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertRedirect('/admin');
    }

    public function test_panel_admin_dibatasi_staf(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['/admin', '/admin/orderan', '/admin/pengguna', '/admin/transaksi', '/admin/paket'] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin')->assertOk()->assertSee('Dashboard Admin');
        $this->get('/admin/orderan')->assertOk()->assertSee('Manajemen Orderan');
        $this->get('/admin/pengguna')->assertOk()->assertSee('Manajemen Pengguna');
        $this->get('/admin/transaksi')->assertOk()->assertSee('Transaksi');
        $this->get('/admin/paket')->assertOk()->assertSee('Paket Layanan');
    }
}
