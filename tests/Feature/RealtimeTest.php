<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Events\NewProjectCreated;
use App\Events\PaymentStatusChanged;
use App\Events\ProjectUpdated;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Payment\SnapGateway;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RealtimeTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'SB-Mid-server-test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.midtrans.server_key' => self::KEY]);
    }

    private function channels($event): array
    {
        return array_map(fn (PrivateChannel $c) => $c->name, $event->broadcastOn());
    }

    private function notify(Payment $payment, string $status)
    {
        $body = [
            'order_id' => $payment->order_id, 'status_code' => '200', 'gross_amount' => '3000000.00',
            'transaction_status' => $status, 'transaction_id' => 't1', 'payment_type' => 'qris',
        ];
        $body['signature_key'] = hash('sha512', $body['order_id'].'200'.'3000000.00'.self::KEY);

        return $this->postJson('/api/payments/notification', $body)->assertOk();
    }

    public function test_orderan_baru_hanya_dikirim_ke_channel_staf(): void
    {
        Event::fake([NewProjectCreated::class]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/projects', [
            'project_title' => 'Toko Online', 'category' => 'website', 'description' => str_repeat('Deskripsi lengkap. ', 6),
            'skills' => 'Laravel', 'contact_name' => 'Budi', 'contact_phone' => '0812',
        ])->assertCreated();

        Event::assertDispatched(NewProjectCreated::class, function ($e) {
            return $this->channels($e) === ['private-admin.orders']
                && $e->broadcastAs() === 'project.created'
                && $e->broadcastWith()['project']['project_title'] === 'Toko Online';
        });
    }

    public function test_perubahan_status_dan_harga_dikirim_ke_pemilik_dan_admin(): void
    {
        Event::fake([ProjectUpdated::class]);
        $project = Project::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/projects/{$project->id}/price", ['plan_price_range' => 3000000])->assertOk();

        Event::assertDispatchedTimes(ProjectUpdated::class, 2);
        Event::assertDispatched(ProjectUpdated::class, function ($e) use ($project) {
            return $this->channels($e) === ["private-App.Models.User.{$project->user_id}", 'private-admin.orders']
                && $e->broadcastWith()['status'] === 'approved';
        });
        Event::assertDispatched(ProjectUpdated::class, fn ($e) => $e->broadcastWith()['plan_price_range'] === '3000000');
    }

    public function test_gagal_atau_tanpa_perubahan_tidak_menyiarkan_event(): void
    {
        Event::fake([ProjectUpdated::class]);
        $project = Project::factory()->create();
        $this->actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'pending'])->assertOk(); // sudah pending
        $this->patchJson("/api/projects/{$project->id}/status", ['status' => 'done'])->assertStatus(409);

        Event::assertNotDispatched(ProjectUpdated::class);
    }

    public function test_pembayaran_dibuat_dan_webhook_menyiarkan_perubahan_sekali(): void
    {
        $this->app->instance(SnapGateway::class, new class implements SnapGateway
        {
            public function createTransaction(array $params): array
            {
                return ['token' => 'tok', 'redirect_url' => 'https://example.test/pay'];
            }
        });
        Event::fake([PaymentStatusChanged::class]);

        $project = Project::factory()->create(['plan_price_range' => '3000000']);
        $project->status = ProjectStatus::Approved;
        $project->save();

        $res = $this->actingAs($project->user)->postJson("/api/payments/create/{$project->id}")->assertCreated();
        Event::assertDispatchedTimes(PaymentStatusChanged::class, 1); // dibuat (pending)

        $payment = Payment::where('order_id', $res->json('data.order_id'))->first();
        $this->notify($payment, 'settlement');
        $this->notify($payment, 'settlement'); // berulang: tidak ada perubahan
        $this->notify($payment, 'expire');     // tidak menurunkan status lunas

        Event::assertDispatchedTimes(PaymentStatusChanged::class, 2);
        Event::assertDispatched(PaymentStatusChanged::class, function ($e) use ($project) {
            $data = $e->broadcastWith();

            return $e->payment->status === PaymentStatus::Paid
                && $this->channels($e) === ["private-App.Models.User.{$project->user_id}", 'private-admin.orders']
                && $e->broadcastAs() === 'payment.changed'
                && ! array_key_exists('snap_token', $data) && ! array_key_exists('snap_url', $data)
                && $data['status'] === PaymentStatus::Paid && $data['amount'] === 3000000.0;
        });
    }
}
