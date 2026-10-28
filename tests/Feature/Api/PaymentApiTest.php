<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Payment\SnapGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-test';

    /** @var array<int, array> */
    private array $snapCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.midtrans.server_key' => self::KEY]);

        $this->app->instance(SnapGateway::class, new class($this->snapCalls) implements SnapGateway
        {
            public function __construct(private array &$calls) {}

            public function createTransaction(array $params): array
            {
                $this->calls[] = $params;

                return ['token' => 'snap-token-123', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/snap-token-123'];
            }
        });
    }

    private function approvedProject(string $price = '3000000', ProjectStatus $status = ProjectStatus::Approved): Project
    {
        $project = Project::factory()->create(['plan_price_range' => $price]);
        $project->status = $status;
        $project->save();

        return $project;
    }

    private function notify(Payment $payment, string $status, array $extra = [], ?string $key = null): TestResponse
    {
        $body = array_merge([
            'order_id' => $payment->order_id,
            'status_code' => '200',
            'gross_amount' => number_format((float) $payment->amount, 2, '.', ''),
            'transaction_status' => $status,
            'transaction_id' => 'trx-1',
            'payment_type' => 'bank_transfer',
        ], $extra);
        $body['signature_key'] = $extra['signature_key']
            ?? hash('sha512', $body['order_id'].$body['status_code'].$body['gross_amount'].($key ?? self::KEY));

        return $this->postJson('/api/payments/notification', $body);
    }

    private function pending(Project $project): Payment
    {
        return Payment::factory()->for($project)->create(['amount' => 3000000]);
    }

    // ── Buat transaksi ──
    public function test_buat_transaksi_memakai_harga_dari_database(): void
    {
        $project = $this->approvedProject('3000000');

        $res = $this->actingAs($project->user)->postJson("/api/payments/create/{$project->id}", ['amount' => 1, 'customerEmail' => 'x@example.test'])
            ->assertCreated()
            ->assertJsonPath('data.amount', 3000000)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.snap_token', 'snap-token-123');

        $this->assertMatchesRegularExpression("/^WEBKITA-{$project->id}-\d+$/", $res->json('data.order_id'));
        $this->assertSame(3000000, $this->snapCalls[0]['transaction_details']['gross_amount']);
        $this->assertSame('x@example.test', $this->snapCalls[0]['customer_details']['email']);
        $this->assertDatabaseHas('payments', ['order_id' => $res->json('data.order_id'), 'user_id' => $project->user_id]);
    }

    public function test_buat_transaksi_aturan_bisnis(): void
    {
        $owner = User::factory()->create();
        $mk = function (array $attrs, ProjectStatus $status) use ($owner) {
            $p = Project::factory()->for($owner)->create($attrs);
            $p->status = $status;
            $p->save();

            return $p;
        };

        $pending = $mk(['plan_price_range' => '3000000'], ProjectStatus::Pending);
        $noPrice = $mk([], ProjectStatus::Approved);
        $paid = $mk(['plan_price_range' => '3000000'], ProjectStatus::Process);
        Payment::factory()->for($paid)->paid()->create();

        $this->actingAs($owner);
        $this->postJson("/api/payments/create/{$pending->id}")->assertStatus(409);
        $this->postJson("/api/payments/create/{$noPrice->id}")->assertStatus(409)->assertJsonPath('message', 'Harga final belum ditetapkan oleh admin');
        $this->postJson("/api/payments/create/{$paid->id}")->assertStatus(409)->assertJsonPath('message', 'Orderan ini sudah dibayar');
        $this->assertSame([], $this->snapCalls);
    }

    public function test_hanya_pemilik_boleh_membayar(): void
    {
        $project = $this->approvedProject();

        $this->postJson("/api/payments/create/{$project->id}")->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson("/api/payments/create/{$project->id}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->postJson("/api/payments/create/{$project->id}")->assertForbidden();
    }

    // ── Webhook ──
    public function test_webhook_settlement_menandai_lunas_tanpa_login(): void
    {
        $payment = $this->pending($this->approvedProject());

        $this->notify($payment, 'settlement')->assertOk()->assertJsonPath('status', 'success');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('trx-1', $payment->transaction_id);
        $this->assertSame('bank_transfer', $payment->payment_type);
    }

    public function test_webhook_menolak_signature_palsu(): void
    {
        $payment = $this->pending($this->approvedProject());

        $this->notify($payment, 'settlement', ['signature_key' => 'palsu'])->assertForbidden();
        $this->notify($payment, 'settlement', [], 'kunci-salah')->assertForbidden();
        $this->postJson('/api/payments/notification', ['order_id' => $payment->order_id])->assertForbidden();
        $this->postJson('/api/payments/notification', [])->assertStatus(400);

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    public function test_webhook_status_lain(): void
    {
        $project = $this->approvedProject();

        foreach (['cancel', 'deny', 'expire'] as $s) {
            $p = $this->pending($project);
            $this->notify($p, $s)->assertOk();
            $this->assertSame(PaymentStatus::Failed, $p->fresh()->status, $s);
        }

        $p = $this->pending($project);
        $this->notify($p, 'capture', ['fraud_status' => 'challenge'])->assertOk();
        $this->assertSame(PaymentStatus::Challenge, $p->fresh()->status);
        $this->notify($p, 'capture', ['fraud_status' => 'accept'])->assertOk();
        $this->assertSame(PaymentStatus::Paid, $p->fresh()->status);
    }

    public function test_webhook_idempoten_dan_tidak_menurunkan_status_lunas(): void
    {
        $payment = $this->pending($this->approvedProject());

        $this->notify($payment, 'settlement')->assertOk();
        $paidAt = $payment->fresh()->paid_at;

        $this->travel(5)->minutes();
        $this->notify($payment, 'settlement')->assertOk();
        $this->notify($payment, 'expire')->assertOk();
        $this->notify($payment, 'pending')->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertEquals($paidAt, $payment->paid_at);
    }

    public function test_webhook_order_tidak_dikenal_dan_nominal_tidak_cocok(): void
    {
        $payment = $this->pending($this->approvedProject());

        $unknown = new Payment(['order_id' => 'TIDAK-ADA', 'amount' => 1000]);
        $this->notify($unknown, 'settlement')->assertOk(); // diabaikan, tetap 200

        $this->notify($payment, 'settlement', ['gross_amount' => '1.00', 'signature_key' => hash('sha512', $payment->order_id.'200'.'1.00'.self::KEY)])
            ->assertStatus(400)->assertJsonPath('message', 'Nominal tidak sesuai');
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    // ── Baca data ──
    public function test_status_transaksi_hanya_pemilik_atau_staf(): void
    {
        $payment = $this->pending($this->approvedProject());
        $dev = User::factory()->create();
        $dev->role = Role::Developer;
        $dev->save();

        $this->actingAs($payment->user)->getJson("/api/payments/status/{$payment->order_id}")->assertOk()->assertJsonPath('data.amount', 3000000);
        $this->actingAs($dev)->getJson("/api/payments/status/{$payment->order_id}")->assertOk();
        $this->actingAs(User::factory()->create())->getJson("/api/payments/status/{$payment->order_id}")->assertForbidden();
        $this->actingAs($payment->user)->getJson('/api/payments/status/TIDAK-ADA')->assertNotFound();
    }

    public function test_semua_transaksi_hanya_admin(): void
    {
        $this->pending($this->approvedProject());

        $this->actingAs(User::factory()->create())->getJson('/api/payments')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->getJson('/api/payments')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cek_lunas_dan_invoice(): void
    {
        $project = $this->approvedProject();
        $project->forceFill(['invoice_number' => 'INV-0001-2026', 'invoice_issued_at' => now()])->save();
        $payment = $this->pending($project);

        $this->actingAs($project->user);
        $this->getJson("/api/payments/project/{$project->id}")->assertOk()->assertJsonPath('isPaid', false);
        $this->getJson("/api/payments/invoice/{$project->id}")->assertOk()
            ->assertJsonPath('data.is_paid', false)->assertJsonPath('data.amount', 3000000)->assertJsonPath('data.invoice_number', 'INV-0001-2026');
        $this->assertArrayNotHasKey('user_id', $this->getJson("/api/payments/invoice/{$project->id}")->json('data'));

        $this->notify($payment, 'settlement');

        $this->getJson("/api/payments/project/{$project->id}")->assertJsonPath('isPaid', true);
        $this->getJson("/api/payments/invoice/{$project->id}")
            ->assertJsonPath('data.is_paid', true)
            ->assertJsonPath('data.payment_reference', $payment->order_id)
            ->assertJsonPath('data.payment_type', 'bank_transfer');

        $this->actingAs(User::factory()->create());
        $this->getJson("/api/payments/project/{$project->id}")->assertForbidden();
        $this->getJson("/api/payments/invoice/{$project->id}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->getJson("/api/payments/invoice/{$project->id}")->assertOk();
    }
}
