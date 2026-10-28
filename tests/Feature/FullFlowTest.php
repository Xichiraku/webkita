<?php

namespace Tests\Feature;

use App\Mail\VerifyEmailMail;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\SnapGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Alur utama dari awal sampai akhir: daftar -> verifikasi -> pesan -> admin setujui & tetapkan harga
 * -> bayar -> webhook Midtrans -> invoice lunas -> admin melihat transaksi.
 */
class FullFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_alur_pemesanan_sampai_pembayaran_lunas(): void
    {
        Mail::fake();
        config(['services.midtrans.server_key' => 'kunci-uji']);
        $this->app->instance(SnapGateway::class, new class implements SnapGateway
        {
            public function createTransaction(array $params): array
            {
                return ['token' => 'snap-123', 'redirect_url' => 'https://example.test/pay'];
            }
        });

        // 1. Daftar & verifikasi email
        $this->post('/register', [
            'username' => 'budi', 'first_name' => 'Budi', 'last_name' => 'Santoso', 'email' => 'budi@example.test',
            'phone' => '081234567890', 'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
        ])->assertRedirect();

        $url = null;
        Mail::assertSent(VerifyEmailMail::class, function ($m) use (&$url) {
            $url = $m->verifyUrl;

            return true;
        });
        $this->get($url)->assertRedirect('/login');

        // 2. Login & pesan paket
        $this->post('/login', ['login' => 'budi', 'password' => 'Rahasia123'])->assertRedirect('/dashboard');
        $this->get('/orderan/baru')->assertOk();

        $projectId = $this->postJson('/api/projects', [
            'plan_title' => 'Starter', 'price_range' => 'Rp 500.000 – Rp 1.500.000', 'project_title' => 'Toko Online Batik',
            'category' => 'website', 'description' => str_repeat('Kebutuhan toko online lengkap. ', 5),
            'skills' => 'Laravel, MySQL', 'contact_name' => 'Budi Santoso', 'contact_phone' => '081234567890',
        ])->assertCreated()->json('data.id');

        $this->get('/orderan')->assertOk()->assertSee('Toko Online Batik');

        // Belum disetujui: belum boleh bayar
        $this->postJson("/api/payments/create/{$projectId}")->assertStatus(409);
        $this->post('/logout');

        // 3. Admin menyetujui & menetapkan harga final
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $this->getJson('/api/projects')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson("/api/projects/{$projectId}/status", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/projects/{$projectId}/price", ['plan_price_range' => 'Rp 1.200.000'])
            ->assertOk()->assertJsonPath('data.invoice_number', sprintf('INV-%04d-%d', $projectId, now()->year));
        $this->post('/logout');

        // 4. Klien membayar
        $this->post('/login', ['login' => 'budi@example.test', 'password' => 'Rahasia123']);
        $this->get("/invoice/{$projectId}")->assertOk()->assertSee('Rp 1.200.000')->assertSee('MENUNGGU PEMBAYARAN')->assertSee('Bayar sekarang');

        $orderId = $this->postJson("/api/payments/create/{$projectId}", ['amount' => 1])
            ->assertCreated()->assertJsonPath('data.amount', 1200000)->json('data.order_id');
        $this->post('/logout');

        // 5. Webhook Midtrans (tanpa login)
        $body = ['order_id' => $orderId, 'status_code' => '200', 'gross_amount' => '1200000.00', 'transaction_status' => 'settlement', 'transaction_id' => 'mid-1', 'payment_type' => 'qris'];
        $body['signature_key'] = hash('sha512', $orderId.'200'.'1200000.00'.'kunci-uji');
        $this->postJson('/api/payments/notification', $body)->assertOk();

        // 6. Invoice lunas & admin melihat transaksi
        $this->post('/login', ['login' => 'budi', 'password' => 'Rahasia123']);
        $this->getJson("/api/payments/project/{$projectId}")->assertJsonPath('isPaid', true);
        $this->get("/invoice/{$projectId}")->assertOk()->assertSee('LUNAS')->assertSee($orderId)->assertDontSee('Bayar sekarang');
        $this->post('/logout');

        $this->actingAs($admin)->getJson('/api/payments')->assertOk()
            ->assertJsonPath('data.0.status', 'paid')->assertJsonPath('data.0.amount', 1200000);
        $this->assertNotNull(Payment::where('order_id', $orderId)->first()->paid_at);
    }
}
