<x-layouts.site :title="$project->project_title">
<main class="relative overflow-hidden bg-ink-50">
    <div class="relative mx-auto max-w-2xl px-5 pb-20 pt-14 sm:px-8">
        <div class="animate-rise rounded-3xl border border-ink-200 bg-white px-6 py-12 text-center shadow-lift sm:px-12">
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-[#FEF3C7] text-[#B45309]"><x-lucide-lock class="h-8 w-8" aria-hidden="true" /></span>
            <h1 class="mt-5 text-2xl font-extrabold tracking-tight text-ink-900">Workspace belum aktif</h1>
            <p class="mt-1 font-semibold text-ink-800">{{ $project->project_title }}</p>
            <p class="mx-auto mt-3 max-w-md text-ink-600">Workspace aktif setelah pembayaran terkonfirmasi.</p>

            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                @if ($role === 'client')
                    @if ($project->plan_price_range && in_array($project->status->value, ['approved', 'process'], true))
                        <a href="{{ route('invoice', $project) }}" class="px-6 py-3 {{ Ui::BTN_PRIMARY }}">Lihat Invoice dan Bayar</a>
                    @else
                        <p class="rounded-xl bg-ink-50 p-3 text-sm text-ink-600">Pembayaran dapat dilakukan setelah proyek disetujui dan harga final ditetapkan.</p>
                    @endif
                    <a href="{{ route('orderan') }}" class="px-6 py-3 {{ Ui::BTN_SECONDARY }}">Kembali ke Orderan</a>
                @else
                    <p class="rounded-xl bg-ink-50 p-3 text-sm text-ink-600">Menunggu pembayaran klien.</p>
                    <a href="{{ route('developer.projects') }}" class="px-6 py-3 {{ Ui::BTN_SECONDARY }}">Kembali ke Proyek Saya</a>
                @endif
            </div>
        </div>
    </div>
</main>
</x-layouts.site>
