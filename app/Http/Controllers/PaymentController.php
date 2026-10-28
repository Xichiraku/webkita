<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Concerns\RespondsWithJson;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Project;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use RespondsWithJson;

    public function __construct(private PaymentService $payments) {}

    /** Semua transaksi (admin). */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Payment::class);

        return $this->ok(PaymentResource::collection(Payment::latest()->get()));
    }

    public function status(string $orderId): JsonResponse
    {
        $payment = Payment::where('order_id', $orderId)->first();

        abort_if($payment === null, 404, 'Transaksi tidak ditemukan');
        $this->authorize('view', $payment);

        return $this->ok(new PaymentResource($payment));
    }

    public function create(Request $request, Project $project): JsonResponse
    {
        $this->authorize('create', [Payment::class, $project]);

        $customer = $request->validate([
            'customerName' => ['nullable', 'string', 'max:255'],
            'customerEmail' => ['nullable', 'email', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:30'],
        ]);

        return $this->ok(new PaymentResource($this->payments->createTransaction($request->user(), $project, $customer)), status: 201);
    }

    /** Webhook Midtrans (tanpa login; diamankan signature). */
    public function notification(Request $request): JsonResponse
    {
        $this->payments->handleNotification($request->json()->all());

        return response()->json(['status' => 'success']);
    }

    public function projectStatus(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'status' => 'success',
            'isPaid' => $project->payments()->where('status', PaymentStatus::Paid->value)->exists(),
        ]);
    }

    public function invoice(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return $this->ok($this->payments->invoice($project));
    }
}
