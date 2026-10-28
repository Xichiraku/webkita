<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Events\PaymentStatusChanged;
use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\Payment\SnapGateway;
use App\Support\Price;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(private SnapGateway $snap, private ProjectActivityLogger $activities) {}

    /**
     * Buat transaksi Snap. Nominal TIDAK diambil dari browser: selalu dari harga final
     * yang ditetapkan admin di database.
     *
     * @param  array{customerName?: ?string, customerEmail?: ?string, customerPhone?: ?string}  $customer
     */
    public function createTransaction(User $user, Project $project, array $customer = []): Payment
    {
        if (! in_array($project->status, [ProjectStatus::Approved, ProjectStatus::Process], true)) {
            throw new ApiException('Pembayaran hanya dapat dilakukan setelah orderan disetujui', 409);
        }

        $amount = Price::parse($project->plan_price_range);
        if (! $amount) {
            throw new ApiException('Harga final belum ditetapkan oleh admin', 409);
        }

        if ($project->payments()->where('status', PaymentStatus::Paid->value)->exists()) {
            throw new ApiException('Orderan ini sudah dibayar', 409);
        }

        $orderId = sprintf('WEBKITA-%d-%d', $project->id, (int) (microtime(true) * 1000));

        $snap = $this->snap->createTransaction([
            'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $amount],
            'customer_details' => [
                'first_name' => $customer['customerName'] ?? null ?: $project->contact_name,
                'email' => $customer['customerEmail'] ?? null ?: $user->email,
                'phone' => $customer['customerPhone'] ?? null ?: $project->contact_phone,
            ],
            'item_details' => [[
                'id' => "project-{$project->id}",
                'price' => $amount,
                'quantity' => 1,
                'name' => "Pembayaran Project #{$project->id}",
            ]],
        ]);

        $payment = new Payment([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'order_id' => $orderId,
            'amount' => $amount,
            'snap_token' => $snap['token'],
            'snap_url' => $snap['redirect_url'],
        ]);
        $payment->status = PaymentStatus::Pending;
        $payment->save();
        $payment->refresh();

        PaymentStatusChanged::dispatch($payment);

        return $payment;
    }

    /**
     * Proses notifikasi Midtrans. Signature diverifikasi agar notifikasi tidak bisa dipalsukan;
     * idempoten dan tidak menurunkan transaksi yang sudah lunas.
     *
     * @param  array<string, mixed>  $n
     */
    public function handleNotification(array $n): void
    {
        $orderId = (string) ($n['order_id'] ?? '');
        if ($orderId === '') {
            throw new ApiException('Order ID tidak ditemukan', 400);
        }

        $expected = hash('sha512', $orderId.($n['status_code'] ?? '').($n['gross_amount'] ?? '').config('services.midtrans.server_key'));
        $signature = (string) ($n['signature_key'] ?? '');

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            throw new ApiException('Signature tidak valid', 403);
        }

        $transactionStatus = $n['transaction_status'] ?? null;
        $status = match (true) {
            $transactionStatus === 'capture' => ($n['fraud_status'] ?? null) === 'challenge' ? PaymentStatus::Challenge : PaymentStatus::Paid,
            $transactionStatus === 'settlement' => PaymentStatus::Paid,
            in_array($transactionStatus, ['cancel', 'deny', 'expire'], true) => PaymentStatus::Failed,
            default => PaymentStatus::Pending,
        };

        DB::transaction(function () use ($orderId, $status, $n) {
            $payment = Payment::where('order_id', $orderId)->lockForUpdate()->first();

            // Order tidak dikenal (mis. tes notifikasi dari dashboard Midtrans): abaikan, balas 200.
            if (! $payment) {
                return;
            }

            if (abs((float) ($n['gross_amount'] ?? 0) - (float) $payment->amount) > 0.01) {
                throw new ApiException('Nominal tidak sesuai', 400);
            }

            // Notifikasi bisa datang tidak berurutan; transaksi lunas tidak diturunkan lagi.
            if ($payment->status === PaymentStatus::Paid && $status !== PaymentStatus::Paid) {
                return;
            }

            $becamePaid = $status === PaymentStatus::Paid && $payment->status !== PaymentStatus::Paid;
            $payment->status = $status;
            if (! empty($n['transaction_id'])) {
                $payment->transaction_id = (string) $n['transaction_id'];
            }
            if (! empty($n['payment_type'])) {
                $payment->payment_type = (string) $n['payment_type'];
            }
            if ($status === PaymentStatus::Paid && ! $payment->paid_at) {
                $payment->paid_at = now();
            }
            $changed = $payment->isDirty();
            $payment->save();

            // Event baru dikirim bila ada perubahan (notifikasi berulang tidak menggandakan update).
            if ($changed) {
                PaymentStatusChanged::dispatch($payment->refresh());
            }

            if ($becamePaid) {
                $this->activities->logSystem($payment->project, 'payment.paid', [
                    'order_id' => $payment->order_id, 'amount' => (int) round((float) $payment->amount), 'method' => $payment->payment_type,
                ]);
            }
        });
    }

    /** Data invoice satu proyek: tanggal terbit, status & referensi pembayaran. */
    public function invoice(Project $project): array
    {
        $paid = $project->payments()
            ->where('status', PaymentStatus::Paid->value)
            ->orderByRaw('paid_at IS NULL')
            ->orderBy('paid_at')
            ->first();

        return [
            'id' => $project->id,
            'project_title' => $project->project_title,
            'category' => $project->category,
            'plan_title' => $project->plan_title,
            'status' => $project->status,
            'contact_name' => $project->contact_name,
            'contact_phone' => $project->contact_phone,
            'invoice_number' => $project->invoice_number,
            'invoice_issued_at' => $project->invoice_issued_at,
            'created_at' => $project->created_at,
            'amount' => Price::parse($project->plan_price_range),
            'is_paid' => $paid !== null,
            'paid_at' => $paid?->paid_at,
            'payment_reference' => $paid?->order_id,
            'payment_type' => $paid?->payment_type,
        ];
    }
}
