@php
    $tips = [
        ['clipboard-check', 'Pertimbangkan Kebutuhan', 'Apakah Anda butuh website sederhana atau platform kompleks dengan banyak fitur?'],
        ['timer', 'Timeline Proyek', 'Proyek mendesak biasanya membutuhkan budget lebih tinggi.'],
        ['life-buoy', 'Maintenance & Support', 'Alokasikan budget untuk pemeliharaan website setelah launching.'],
        ['trending-up', 'Skalabilitas', 'Pikirkan pertumbuhan bisnis untuk 1-2 tahun ke depan.'],
    ];
@endphp
<x-layouts.site title="Harga">
<main class="relative overflow-hidden bg-ink-50"
      x-data="pricingCalc(@js($plans), @js(auth()->check()), @js(route('orderan.baru')))">
    <div class="pointer-events-none absolute -left-24 top-0 h-80 w-80 animate-blob rounded-full bg-brand-100 opacity-70 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -right-24 top-72 h-80 w-80 animate-blob rounded-full bg-brand-200/50 blur-3xl" style="animation-delay: -7s" aria-hidden="true"></div>

    {{-- ================= JUDUL ================= --}}
    <section class="relative mx-auto max-w-7xl px-5 pb-10 pt-14 text-center sm:px-8 lg:pt-20">
        <h1 class="reveal text-4xl font-extrabold tracking-tight text-ink-900 sm:text-5xl">Paket Layanan Kami</h1>
        <p class="reveal mx-auto mt-4 max-w-2xl text-lg text-ink-600" style="--d: 100ms">
            Pilih paket yang sesuai dengan kebutuhan bisnis Anda. Dari UMKM hingga enterprise, kami siap membantu mewujudkan website impian Anda.
        </p>
    </section>

    @if (count($plans) === 0)
        <section class="relative mx-auto max-w-3xl px-5 pb-20 sm:px-8">
            <p class="rounded-3xl border border-ink-200 bg-white p-10 text-center font-semibold text-ink-600 shadow-soft">Belum ada paket layanan.</p>
        </section>
    @else
        {{-- ================= KALKULATOR BUDGET ================= --}}
        <section class="relative mx-auto max-w-3xl px-5 sm:px-8" aria-labelledby="budget-title">
            <div class="reveal rounded-3xl border border-ink-200 bg-white p-6 shadow-soft sm:p-8" style="--d: 150ms">
                <div class="flex items-start gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-100 text-brand-700"><x-lucide-wallet class="h-5 w-5" aria-hidden="true" /></span>
                    <div>
                        <h2 id="budget-title" class="text-xl font-extrabold text-ink-900 sm:text-2xl">Berapa Budget Anda?</h2>
                        <p class="text-sm text-ink-600">Geser slider untuk melihat rekomendasi paket dan fitur.</p>
                    </div>
                </div>

                <div class="mt-7">
                    <div class="flex items-end justify-between gap-4">
                        <label for="budget" class="text-sm font-bold text-ink-700">Budget Anda</label>
                        <output for="budget" class="whitespace-nowrap text-2xl font-extrabold tabular-nums tracking-tight text-ink-900 min-[420px]:text-3xl sm:text-4xl" aria-hidden="true"
                                x-text="rp(budget) + (budget >= MAX ? '+' : '')"></output>
                    </div>

                    <div class="relative mt-5">
                        <input id="budget" class="budget-range" type="range" min="0" :max="STEPS" step="1" x-model.number="t"
                               :style="`--p: ${(t / STEPS).toFixed(4)}`"
                               @pointerup="say()" @keyup="say()"
                               :aria-valuetext="`${rp(budget)}, paket ${current.title}`" aria-describedby="budget-help">
                        <div class="pointer-events-none absolute inset-x-0 top-[28px] h-3" aria-hidden="true">
                            <template x-for="(p, i) in zones.ticks" :key="i"><span class="absolute top-0 h-2.5 w-px bg-ink-300" :style="`left: ${left(p)}`"></span></template>
                        </div>
                    </div>
                    <div class="relative mt-5 h-5 text-[11px] font-semibold text-ink-600 sm:text-xs" aria-hidden="true">
                        <template x-for="z in zones.labels" :key="z.p.id">
                            <span class="absolute -translate-x-1/2 whitespace-nowrap transition-colors" :class="z.p.id === current.id ? 'font-extrabold text-brand-700' : ''" :style="`left: ${left(z.c)}`">
                                <template x-if="z.p.title.length > 9"><span><span class="sm:hidden" x-text="z.p.title.slice(0, 3)"></span><span class="hidden sm:inline" x-text="z.p.title"></span></span></template>
                                <template x-if="z.p.title.length <= 9"><span x-text="z.p.title"></span></template>
                            </span>
                        </template>
                    </div>

                    <div class="mt-1 flex items-center justify-between text-xs text-ink-600"><span>Rp 500 Ribu</span><span>Rp 100 Juta</span></div>
                    <p id="budget-help" class="sr-only">Gunakan tombol panah kiri dan kanan untuk mengubah budget. Rekomendasi paket diperbarui otomatis.</p>
                    <p class="sr-only" role="status" aria-live="polite" x-text="announce"></p>
                </div>

                <div class="mt-7 border-t border-ink-200 pt-6">
                    <div :key="current.id" class="animate-rise">
                        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                            <p class="flex items-center gap-2 font-bold text-ink-900"><span class="h-3 w-3 rounded-full" :class="barOf(current)"></span><span>Paket Rekomendasi: <span x-text="current.title"></span></span></p>
                            <p class="text-sm font-semibold text-ink-600" x-text="current.priceRange"></p>
                        </div>
                        <p class="mt-5 flex items-center gap-2 font-extrabold text-ink-900">
                            <span class="grid h-7 w-7 place-items-center rounded-full bg-brand-100 text-brand-700"><x-lucide-check class="h-4 w-4" aria-hidden="true" /></span>
                            Yang Anda Dapatkan:
                        </p>
                        <ul class="mt-3 grid gap-2.5 text-sm text-ink-700 sm:grid-cols-2">
                            <template x-for="f in current.features" :key="f">
                                <li class="flex items-start gap-2.5"><x-lucide-check class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" aria-hidden="true" /><span x-text="f"></span></li>
                            </template>
                        </ul>
                        <p class="mt-4 text-sm text-brand-700" x-show="current.upgradeNote" x-text="current.upgradeNote"></p>
                        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                            <button type="button" @click="order(current)" class="px-5 py-3 {{ Ui::BTN_PRIMARY }}">Pesan Paket <span x-text="current.title" class="ml-1"></span></button>
                            <button type="button" @click="detail = current" class="px-5 py-3 {{ Ui::BTN_SECONDARY }}">Lihat Detail</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================= DAFTAR PAKET ================= --}}
        <section class="relative mx-auto max-w-7xl px-5 pb-6 pt-14 sm:px-8" aria-label="Daftar paket">
            <div class="grid items-stretch gap-6 pt-3 sm:grid-cols-2 lg:grid-cols-4">
                <template x-for="(p, i) in plans" :key="p.id">
                    <article class="relative flex flex-col rounded-3xl border bg-white p-6 transition duration-300"
                             :class="p.id === current.id ? 'border-brand-500 shadow-lift ring-2 ring-brand-200' : 'border-ink-200 shadow-soft'">
                        <span x-show="p.id === current.id" class="pointer-events-none absolute -top-3 left-6 inline-flex animate-pop items-center gap-1.5 rounded-full bg-brand-600 px-3 py-1 text-xs font-bold text-white shadow-soft">
                            <x-lucide-sparkles class="h-3.5 w-3.5" aria-hidden="true" />Rekomendasi Anda
                        </span>
                        <div class="h-1.5 rounded-full" :class="barOf(p)"></div>
                        <h3 class="mt-5 text-xl font-extrabold text-ink-900" x-text="p.title"></h3>
                        <p class="mt-1 text-sm font-semibold text-ink-600" x-text="p.priceRange"></p>
                        <ul class="mb-6 mt-5 space-y-2.5 text-sm text-ink-700">
                            <template x-for="f in p.features.slice(0, 5)" :key="f">
                                <li class="flex items-start gap-2.5"><x-lucide-check class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" aria-hidden="true" /><span x-text="f"></span></li>
                            </template>
                        </ul>
                        <button type="button" @click="detail = p" class="mt-auto w-full px-4 py-2.5 text-sm {{ Ui::BTN_SECONDARY }}">
                            Lihat Detail<span class="sr-only"> paket <span x-text="p.title"></span></span>
                        </button>
                    </article>
                </template>
            </div>
        </section>
    @endif

    {{-- ================= TIPS ================= --}}
    <section class="relative mx-auto max-w-7xl px-5 py-14 sm:px-8">
        <div class="reveal rounded-3xl bg-brand-50 p-7 ring-1 ring-brand-100 sm:p-10">
            <h2 class="text-2xl font-extrabold tracking-tight text-ink-900">Tips Menentukan Budget</h2>
            <div class="mt-8 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($tips as [$icon, $title, $desc])
                    <div>
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-white text-brand-700 shadow-sm"><x-dynamic-component :component="'lucide-'.$icon" class="h-5 w-5" aria-hidden="true" /></span>
                        <h3 class="mt-4 font-bold text-ink-900">{{ $title }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-ink-600">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ================= CTA ================= --}}
    <section class="relative mx-auto max-w-7xl px-5 pb-20 sm:px-8">
        <div class="reveal relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-brand-700 to-brand-900 px-6 py-14 text-center shadow-lift sm:px-12 lg:py-16">
            <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 animate-blob rounded-full bg-brand-400/30 blur-3xl" aria-hidden="true"></div>
            <div class="relative">
                <h2 class="text-3xl font-extrabold tracking-tight text-white">Butuh Konsultasi Paket?</h2>
                <p class="mx-auto mt-3 max-w-2xl text-lg text-brand-100">Tim kami siap membantu Anda menemukan solusi terbaik untuk kebutuhan bisnis Anda. Hubungi kami untuk konsultasi gratis!</p>
                <a href="{{ Ui::waPaket() }}" target="_blank" rel="noopener noreferrer" class="group mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-7 py-3.5 font-bold text-brand-700 shadow-soft transition hover:-translate-y-0.5 hover:bg-brand-50 hover:shadow-lift focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                    <x-lucide-message-circle class="h-5 w-5" aria-hidden="true" /> Hubungi Kami di WhatsApp
                    <x-lucide-arrow-right class="h-5 w-5 transition-transform group-hover:translate-x-1" aria-hidden="true" />
                </a>
            </div>
        </div>
    </section>

    {{-- ================= DETAIL PAKET ================= --}}
    <x-modal show="detail" labelledby="detail-title">
        <template x-if="detail">
            <div class="p-6 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-bold text-brand-700">Detail paket<span x-show="detail.badge" x-text="' · ' + detail.badge"></span></p>
                        <h2 id="detail-title" class="text-2xl font-extrabold tracking-tight">Paket <span x-text="detail.title"></span></h2>
                        <p class="mt-1 text-ink-600" x-text="detail.priceRange + (detail.timeline && detail.timeline !== '-' ? ' · ' + detail.timeline : '')"></p>
                    </div>
                    <button type="button" @click="detail = null" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-ink-600 transition hover:bg-ink-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-400" aria-label="Tutup detail">
                        <x-lucide-x class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>
                <p class="mt-4 text-sm leading-relaxed text-ink-700" x-show="detail.description" x-text="detail.description"></p>
                <h3 class="mt-6 text-sm font-bold uppercase tracking-wide text-ink-600">Yang Anda dapatkan</h3>
                <ul class="mt-3 space-y-2.5">
                    <template x-for="f in detail.features" :key="f">
                        <li class="flex items-start gap-2.5 text-ink-800"><x-lucide-check class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" aria-hidden="true" /><span x-text="f"></span></li>
                    </template>
                </ul>
                <template x-if="detail.notIncluded.length">
                    <div>
                        <h3 class="mt-6 text-sm font-bold uppercase tracking-wide text-ink-600">Tidak termasuk</h3>
                        <ul class="mt-3 space-y-2.5">
                            <template x-for="f in detail.notIncluded" :key="f">
                                <li class="flex items-start gap-2.5 text-ink-700"><x-lucide-x class="mt-0.5 h-4 w-4 shrink-0 text-ink-500" aria-hidden="true" /><span x-text="f"></span></li>
                            </template>
                        </ul>
                    </div>
                </template>
                <p class="mt-6 rounded-xl bg-ink-50 p-3.5 text-sm text-ink-600">Rentang harga adalah estimasi. Harga final ditentukan setelah kebutuhan proyek Anda dikonfirmasi.</p>
                <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                    <button type="button" @click="order(detail)" class="flex-1 px-5 py-3 shadow-none hover:translate-y-0 {{ Ui::BTN_PRIMARY }}">Pesan Paket Ini</button>
                    <button type="button" @click="detail = null" class="px-5 py-3 {{ Ui::BTN_SECONDARY }}">Tutup</button>
                </div>
            </div>
        </template>
    </x-modal>

    {{-- ================= PERLU LOGIN ================= --}}
    <x-modal show="loginPrompt" labelledby="login-title" width="w-[min(92vw,26rem)]">
        <div class="p-6 text-center sm:p-8">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-100 text-brand-700"><x-lucide-log-in class="h-6 w-6" aria-hidden="true" /></span>
            <h2 id="login-title" class="mt-4 text-2xl font-extrabold tracking-tight">Login Diperlukan</h2>
            <p class="mt-2 text-ink-600">Masuk atau buat akun terlebih dahulu untuk memesan paket layanan kami.</p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <button type="button" @click="loginPrompt = false" class="flex-1 px-5 py-3 {{ Ui::BTN_SECONDARY }}">Batal</button>
                <a href="{{ route('login') }}" class="flex-1 px-5 py-3 {{ Ui::BTN_PRIMARY }}">Masuk Sekarang</a>
            </div>
            <p class="mt-4 text-sm text-ink-600">Belum punya akun? <a href="{{ route('register') }}" class="font-bold text-brand-700 hover:underline">Daftar</a></p>
        </div>
    </x-modal>
</main>
</x-layouts.site>
