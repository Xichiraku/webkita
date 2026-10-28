@php
    $isPaid = $invoice['is_paid'];
    $number = $invoice['invoice_number'] ?: sprintf('INV-%04d', $invoice['id']);
    $amount = (int) $invoice['amount'];
    $total = $amount > 0 ? Ui::rp($amount) : 'Belum ditetapkan';
    $status = $invoice['status'];
    $canPay = ! $isPaid && in_array($status->value, ['approved', 'process'], true) && $amount > 0 && auth()->id() === $project->user_id;
    $plan = $invoice['plan_title'] ?: '–';
@endphp
<x-layouts.base title="Invoice {{ $number }}" bodyClass="min-h-screen bg-ink-50 font-sans text-ink-900 antialiased selection:bg-brand-200 print:bg-white">
<div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden bg-gradient-to-b from-white to-ink-50 print:hidden" aria-hidden="true">
    <div class="absolute -left-24 top-10 h-80 w-80 animate-blob rounded-full bg-brand-100 opacity-80 blur-3xl"></div>
    <div class="absolute -right-24 bottom-10 h-96 w-96 animate-blob rounded-full bg-brand-200/60 blur-3xl" style="animation-delay: -7s"></div>
</div>

<div class="mx-auto max-w-3xl px-4 py-8 sm:py-10 print:max-w-none print:p-0"
     x-data="{ paying: false,
        init() {
            // Muat ulang invoice saat harga/pembayaran proyek ini berubah (Reverb).
            const ch = userChannel();
            if (!ch) return;
            const reload = () => setTimeout(() => location.reload(), 800);
            ch.listen('.payment.changed', (e) => { if (e.project_id === {{ $invoice['id'] }} && e.status === 'paid') { Alpine.store('toast').show('Pembayaran diterima. Memperbarui invoice…'); reload(); } })
              .listen('.project.updated', (e) => { if (e.id === {{ $invoice['id'] }} && (e.invoice_number !== @js($invoice['invoice_number']) || e.status !== @js($invoice['status']->value))) reload(); });
        },
        async pay() {
            this.paying = true;
            const toast = Alpine.store('toast');
            try {
                await payProject({{ $invoice['id'] }}, { customerName: @js($invoice['contact_name']), customerEmail: @js(auth()->user()->email), customerPhone: @js($invoice['contact_phone']) }, {
                    onSuccess: () => { toast.show('Pembayaran berhasil. Status invoice diperbarui setelah dikonfirmasi.'); setTimeout(() => location.reload(), 1500); },
                    onPending: () => toast.show('Pembayaran pending. Silakan selesaikan pembayaran Anda.'),
                    onError: () => toast.show('Pembayaran gagal. Silakan coba lagi.', 'err'),
                });
            } catch (e) { toast.show(e.message || 'Terjadi kesalahan saat memproses pembayaran', 'err'); }
            finally { this.paying = false; }
        } }">

    <nav class="mb-4 flex flex-wrap items-center justify-between gap-3 print:hidden" aria-label="Aksi invoice">
        <a href="{{ route('orderan') }}" class="min-h-[2.75rem] border px-4 text-sm {{ Ui::BTN_SECONDARY }}"><x-lucide-arrow-left class="h-4 w-4" aria-hidden="true" /> Kembali</a>
        <div class="flex flex-wrap gap-2">
            @if ($canPay)
                <button type="button" @click="pay()" :disabled="paying" class="min-h-[2.75rem] px-4 text-sm disabled:cursor-wait {{ Ui::BTN_PRIMARY }}">
                    <x-button-spinner x-show="paying" class="h-4 w-4" />
                    <x-lucide-credit-card x-show="!paying" class="h-4 w-4" aria-hidden="true" />
                    <span x-text="paying ? 'Memproses…' : 'Bayar sekarang'"></span>
                </button>
            @endif
            <button type="button" onclick="window.print()" class="min-h-[2.75rem] border border-brand-700 px-4 text-sm text-brand-700 {{ Ui::BTN_SECONDARY }}"><x-lucide-printer class="h-4 w-4" aria-hidden="true" /> Cetak / Simpan PDF</button>
        </div>
    </nav>

    <article class="overflow-hidden rounded-3xl bg-white shadow-lift print:rounded-none print:shadow-none" aria-labelledby="inv-no">
        <header class="keep-color bg-gradient-to-br from-brand-900 to-brand-600 px-6 py-8 text-white sm:px-11 sm:py-9 print:px-8 print:py-6">
            <div class="flex flex-wrap items-start justify-between gap-5">
                <div>
                    <p class="text-3xl font-black tracking-tight">Webkita</p>
                    <p class="mt-1 max-w-[15rem] text-xs leading-relaxed text-brand-100">Hubungkan bisnis Anda dengan developer terbaik</p>
                </div>
                <div class="text-left sm:text-right">
                    <p class="text-xs font-bold uppercase tracking-[.14em] text-brand-100">Invoice</p>
                    <h1 id="inv-no" class="mb-2 mt-1 text-xl font-extrabold">{{ $number }}</h1>
                    @if ($isPaid)
                        <span class="keep-color inline-flex items-center gap-1.5 rounded-full bg-[#DCFCE7] px-3 py-1 text-xs font-extrabold tracking-wide text-[#15803D]"><x-lucide-circle-check class="h-3.5 w-3.5" aria-hidden="true" />LUNAS</span>
                    @else
                        <span class="keep-color inline-flex items-center gap-1.5 rounded-full bg-[#FEF3C7] px-3 py-1 text-xs font-extrabold tracking-wide text-[#B45309]"><x-lucide-clock class="h-3.5 w-3.5" aria-hidden="true" />MENUNGGU PEMBAYARAN</span>
                    @endif
                </div>
            </div>
        </header>

        <div class="px-6 py-8 sm:px-11 sm:py-9 print:px-8 print:py-6">
            <section class="mb-7 grid gap-4 border-b border-ink-100 pb-7 sm:grid-cols-3 sm:gap-5" aria-label="Informasi invoice">
                <div><p class="mb-1 text-xs font-extrabold uppercase tracking-wider text-ink-500">Tanggal invoice</p><p class="text-sm font-semibold">{{ $invoice['invoice_issued_at'] ? Ui::dateLong($invoice['invoice_issued_at']) : 'Belum terbit' }}</p></div>
                <div><p class="mb-1 text-xs font-extrabold uppercase tracking-wider text-ink-500">Tanggal proyek</p><p class="text-sm font-semibold">{{ Ui::dateLong($invoice['created_at']) }}</p></div>
                <div><p class="mb-1 text-xs font-extrabold uppercase tracking-wider text-ink-500">ID proyek</p><p class="break-all text-sm font-semibold">#{{ $invoice['id'] }}</p></div>
            </section>

            <section class="mb-7 grid gap-5 sm:grid-cols-2 sm:gap-6">
                <div>
                    <p class="mb-1 text-xs font-extrabold uppercase tracking-wider text-ink-500">Dari</p>
                    <div class="mt-2 flex items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700"><x-lucide-building-2 class="h-5 w-5" aria-hidden="true" /></span>
                        <div><p class="text-sm font-bold">Webkita</p><p class="text-xs text-ink-600">webkita.com</p></div>
                    </div>
                </div>
                <div>
                    <p class="mb-1 text-xs font-extrabold uppercase tracking-wider text-ink-500">Kepada</p>
                    <div class="mt-2 flex items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700"><x-lucide-user-round class="h-5 w-5" aria-hidden="true" /></span>
                        <div class="min-w-0"><p class="truncate text-sm font-bold">{{ $invoice['contact_name'] ?: '–' }}</p><p class="text-xs text-ink-600">{{ $invoice['contact_phone'] ?: '–' }}</p></div>
                    </div>
                </div>
            </section>

            <section class="mb-7 rounded-2xl border border-ink-100 bg-ink-50 p-4 sm:p-5">
                <h2 class="mb-3 text-sm font-extrabold text-ink-800">Rincian Proyek</h2>
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-ink-200 text-left text-xs font-bold uppercase tracking-wider text-ink-500">
                            <th scope="col" class="py-2 pr-3">Deskripsi</th>
                            <th scope="col" class="hidden px-3 py-2 text-center sm:table-cell">Paket</th>
                            <th scope="col" class="py-2 pl-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="align-top">
                            <td class="py-4 pr-3">
                                <p class="font-bold">{{ $invoice['project_title'] ?: '–' }}</p>
                                <p class="mt-1 text-xs text-ink-600">{{ Ui::category($invoice['category']) }}</p>
                                <span class="keep-color mt-2 inline-block rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700 sm:hidden">{{ $plan }}</span>
                            </td>
                            <td class="hidden px-3 py-4 text-center sm:table-cell"><span class="keep-color inline-block rounded-full bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-700">{{ $plan }}</span></td>
                            <td class="whitespace-nowrap py-4 pl-3 text-right font-semibold">{{ $total }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="mb-7 ml-auto w-full sm:max-w-xs" aria-label="Total pembayaran">
                <div class="flex justify-between gap-4 border-b border-ink-100 py-2 text-sm text-ink-600"><span>Subtotal</span><strong class="text-ink-800">{{ $total }}</strong></div>
                <div class="flex justify-between gap-4 border-b border-ink-100 py-2 text-sm text-ink-600"><span>Pajak (0%)</span><strong class="text-ink-800">Rp 0</strong></div>
                <div class="flex items-baseline justify-between gap-4 pt-3 font-extrabold"><span class="text-base">Total</span><strong class="text-xl text-brand-800">{{ $total }}</strong></div>
            </section>

            @if ($isPaid)
                <section class="keep-color mb-7 flex items-start gap-3 rounded-2xl border border-[#86EFAC] bg-[#DCFCE7] p-4 text-[#15803D]">
                    <x-lucide-circle-check class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                    <div>
                        <p class="text-sm font-extrabold">Pembayaran Telah Diterima</p>
                        <p class="mt-0.5 text-sm">Terima kasih! Pembayaran kamu sudah dikonfirmasi.</p>
                        <dl class="mt-2 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                            @if ($invoice['paid_at'])<div><dt class="inline font-bold">Tanggal bayar: </dt><dd class="inline">{{ Ui::dateLong($invoice['paid_at']) }}</dd></div>@endif
                            @if ($invoice['payment_reference'])<div class="min-w-0"><dt class="inline font-bold">Referensi: </dt><dd class="inline break-all">{{ $invoice['payment_reference'] }}</dd></div>@endif
                            @if ($invoice['payment_type'])<div><dt class="inline font-bold">Metode: </dt><dd class="inline">{{ str_replace('_', ' ', $invoice['payment_type']) }}</dd></div>@endif
                        </dl>
                    </div>
                </section>
            @else
                <section class="keep-color mb-7 flex items-start gap-3 rounded-2xl border border-[#FCD34D] bg-[#FEF3C7] p-4 text-[#B45309] print:break-inside-avoid">
                    <x-lucide-clock class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                    <div>
                        <p class="text-sm font-extrabold">Menunggu pembayaran</p>
                        <p class="mt-0.5 text-sm">
                            @if ($canPay)
                                Selesaikan pembayaran lewat tombol <strong>Bayar sekarang</strong>. Status invoice berubah otomatis setelah pembayaran terkonfirmasi.
                            @else
                                Pembayaran dapat dilakukan setelah proyek disetujui dan harga final ditetapkan oleh admin.
                            @endif
                        </p>
                    </div>
                </section>
            @endif

            <footer class="border-t border-ink-100 pt-5 text-center text-xs leading-relaxed text-ink-500">
                Terima kasih telah mempercayakan proyek Anda kepada <strong class="text-ink-700">Webkita</strong>. Jika ada pertanyaan, hubungi kami di
                <a href="mailto:support@webkita.com" class="font-semibold text-brand-700 underline">support@webkita.com</a>
            </footer>
        </div>
    </article>
</div>
</x-layouts.base>
