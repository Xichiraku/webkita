<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\PricingPlan;
use App\Models\Project;
use App\Services\PaymentService;
use App\Services\PricingService;
use App\Support\Price;
use App\Support\Ui;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman Blade (situs publik, area klien, dan panel admin).
 * Data yang berubah-ubah (admin) diambil lewat /api/* dari browser; halaman klien dirender di server.
 */
class PageController extends Controller
{
    /** Estimasi lama pengerjaan (hari) per paket, untuk "Target Selesai". */
    private const PLAN_DAYS = ['Starter' => 14, 'Professional' => 30, 'Business' => 60, 'Enterprise' => 120];

    public function __construct(private PricingService $pricing, private PaymentService $payments) {}

    public function home(): View
    {
        return view('pages.home');
    }

    public function carakerja(): View
    {
        return view('pages.carakerja');
    }

    public function harga(): View
    {
        return view('pages.harga', [
            'plans' => $this->pricing->getAllActive()->sortBy('min_price')->map(fn ($p) => $this->planForView($p))->values(),
        ]);
    }

    /** Pengguna masuk ke area yang sesuai: staf ke panel admin, lainnya ke beranda. */
    public function dashboard(Request $request): RedirectResponse
    {
        return redirect()->route($request->user()->isStaff() ? 'admin.dashboard' : 'orderan');
    }

    // ───────────── Area klien ─────────────

    public function orderan(Request $request): View
    {
        $projects = $request->user()->projects()->with('payments')->latest()->get();

        $orders = $projects->map(function (Project $p) {
            $final = Price::parse($p->plan_price_range);
            $target = $p->created_at->copy()->addDays(self::PLAN_DAYS[$p->plan_title] ?? 30);

            return [
                'id' => $p->id,
                'projectTitle' => $p->project_title,
                'category' => Ui::category($p->category),
                'description' => $p->description,
                'skills' => $p->skills,
                'contactName' => $p->contact_name,
                'contactPhone' => $p->contact_phone,
                'additionalNotes' => (string) $p->additional_notes,
                'planTitle' => $p->plan_title,
                'estimateRange' => $p->price_range,
                'finalPriceRaw' => $p->plan_price_range ?: '',
                'finalPrice' => $final ? Ui::rp($final) : '',
                'rejectReason' => (string) $p->reject_reason,
                'status' => $p->status->value,
                'createdAt' => Ui::date($p->created_at),
                'target' => Ui::dateLong($target),
                'isPaid' => $p->payments->contains(fn ($x) => $x->status === PaymentStatus::Paid),
                'invoiceUrl' => route('invoice', $p),
                'workspaceUrl' => route('orderan.show', $p),
            ];
        })->values();

        return view('pages.orderan', ['orders' => $orders]);
    }

    /** Formulir pengajuan proyek (3 langkah) untuk paket terpilih (?plan=<id>). */
    public function orderBaru(Request $request): View
    {
        $plan = $request->query('plan') ? PricingPlan::with(['features', 'notIncluded'])->where('is_active', true)->find($request->query('plan')) : null;

        return view('pages.orderan-baru', ['plan' => $plan ? $this->planForView($plan) : null]);
    }

    public function invoice(Project $project): View
    {
        $this->authorize('view', $project);

        return view('pages.invoice', ['project' => $project, 'invoice' => $this->payments->invoice($project)]);
    }

    private function planForView(PricingPlan $p): array
    {
        return [
            'id' => $p->id,
            'title' => $p->title,
            'minPrice' => (float) $p->min_price,
            'maxPrice' => $p->max_price === null ? null : (float) $p->max_price,
            'priceRange' => $p->price_range,
            'tier' => $p->tier,
            'timeline' => $p->timeline ?: '-',
            'badge' => (string) $p->badge,
            'description' => (string) $p->description,
            'features' => $p->features->pluck('feature')->values(),
            'notIncluded' => $p->notIncluded->pluck('feature')->values(),
            'upgradeNote' => (string) $p->upgrade_note,
        ];
    }
}
