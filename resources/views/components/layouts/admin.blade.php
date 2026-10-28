@props(['title' => null, 'section' => 'dashboard', 'crumb' => null])
@php
    $user = auth()->user();
    $nav = [
        ['dashboard', route('admin.dashboard'), 'Dashboard', 'layout-dashboard', null],
        ['orderan', route('admin.orders'), 'Orderan', 'package', 'orders.length'],
        ['pengguna', route('admin.users'), 'Pengguna', 'users', 'users.length'],
        ['transaksi', route('admin.transactions'), 'Transaksi', 'credit-card', 'trx.length'],
        ['paket', route('admin.pricing'), 'Paket Layanan', 'tags', null],
    ];
@endphp
<x-layouts.base :title="($title ?? 'Admin').' · Admin'" bodyClass="admin-bg min-h-screen font-sans text-ink-900 antialiased">
<div x-data="adminApp(@js(['categories' => \App\Support\Ui::CATEGORIES, 'meId' => $user->id, 'isAdmin' => $user->isAdmin()]))" @keydown.escape.window="sideOpen = false">
    <a href="#utama" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[90] focus:rounded-lg focus:bg-brand-700 focus:px-4 focus:py-2 focus:text-white">Lewati ke konten utama</a>

    {{-- overlay mobile --}}
    <div x-show="sideOpen" x-cloak class="fixed inset-0 z-40 bg-ink-950/50 backdrop-blur-sm lg:hidden" aria-hidden="true" @click="sideOpen = false"></div>

    <aside id="admin-sidebar" aria-label="Menu admin" class="card fixed inset-y-0 left-0 z-50 flex w-64 flex-col rounded-r-3xl transition-transform duration-300 lg:inset-y-4 lg:left-4 lg:translate-x-0 lg:rounded-3xl"
           :class="sideOpen ? 'translate-x-0' : '-translate-x-full'">
        <div class="flex items-center justify-between gap-3 px-5 pb-3 pt-6">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-lg text-left focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-400">
                <span class="grid h-11 w-11 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-lg font-extrabold text-white shadow-soft">W</span>
                <span class="leading-tight"><span class="block text-lg font-extrabold text-ink-900">Webkita</span><span class="block text-xs font-semibold text-ink-600">Admin Panel</span></span>
            </a>
            <button type="button" @click="sideOpen = false" class="grid h-9 w-9 place-items-center rounded-xl border border-ink-200 text-ink-700 transition hover:bg-ink-50 lg:hidden" aria-label="Tutup menu"><x-lucide-x class="h-4 w-4" aria-hidden="true" /></button>
        </div>

        <nav class="mt-3 flex-1 space-y-1.5 overflow-y-auto px-3" aria-label="Navigasi admin">
            @foreach ($nav as [$key, $href, $label, $icon, $count])
                @php($on = $section === $key)
                <a href="{{ $href }}" @if ($on) aria-current="page" @endif
                   class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-400 {{ $on ? 'bg-brand-100 font-bold text-brand-800 shadow-[0_1px_0_#fff_inset,0_8px_16px_-10px_rgba(37,89,204,.5)]' : 'font-semibold text-ink-700 hover:bg-brand-50 hover:text-ink-900' }}">
                    <x-dynamic-component :component="'lucide-'.$icon" class="h-[18px] w-[18px]" aria-hidden="true" /> {{ $label }}
                    @if ($count)
                        <span class="ml-auto grid min-w-[1.4rem] place-items-center rounded-full px-1.5 text-xs font-bold {{ $on ? 'bg-white/80 text-brand-800' : 'bg-ink-100 text-ink-700' }}" x-text="{{ $count }}"></span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="space-y-3 p-3">
            <div class="rounded-2xl bg-gradient-to-br from-brand-600 to-brand-800 p-4 text-white shadow-soft">
                <p class="text-xs font-semibold text-brand-100">Perlu tindakan</p>
                <p class="mt-1 text-3xl font-extrabold tabular-nums" x-text="needAction">0</p>
                <a href="{{ route('admin.orders') }}" class="mt-2 inline-flex items-center gap-1 rounded text-sm font-bold text-white underline-offset-4 hover:underline">Lihat daftar <x-lucide-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
            </div>
            <a href="{{ url('/') }}" class="flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold text-ink-700 transition hover:bg-brand-50"><x-lucide-globe class="h-[18px] w-[18px]" aria-hidden="true" /> Lihat Situs</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold text-[#B91C1C] transition hover:bg-[#FEE2E2] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#DC2626]"><x-lucide-log-out class="h-[18px] w-[18px]" aria-hidden="true" /> Keluar</button>
            </form>
        </div>
    </aside>

    <div class="lg:pl-[18.5rem]">
        <header class="flex flex-wrap items-center gap-3 px-4 pt-4 sm:px-8 lg:pr-8 lg:pt-5">
            <button type="button" @click="sideOpen = true" class="card grid h-12 w-12 shrink-0 place-items-center rounded-2xl text-ink-800 transition hover:bg-white lg:hidden" aria-label="Buka menu" aria-controls="admin-sidebar" :aria-expanded="sideOpen"><x-lucide-menu class="h-5 w-5" aria-hidden="true" /></button>

            @if ($section !== 'dashboard')
                <nav aria-label="Breadcrumb" class="hidden text-sm text-ink-600 sm:block">
                    <a href="{{ route('admin.dashboard') }}" class="rounded font-semibold hover:text-brand-700 hover:underline">Dashboard</a>
                    <span aria-hidden="true">/</span> <span aria-current="page" class="font-bold text-ink-900">{{ $crumb ?? $title }}</span>
                </nav>
            @endif

            <div class="ml-auto flex items-center gap-3">
                <button type="button" @click="refresh()" class="card grid h-12 w-12 place-items-center rounded-2xl text-ink-800 transition hover:bg-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-400" aria-label="Muat ulang data">
                    <x-lucide-refresh-cw class="h-5 w-5" ::class="loading ? 'animate-spin' : ''" aria-hidden="true" />
                </button>
                <div class="card hidden items-center gap-3 rounded-2xl py-2 pl-2 pr-5 sm:flex">
                    <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-100 text-sm font-extrabold text-brand-800">{{ Ui::initials($user->name) }}</span>
                    <span class="leading-tight"><span class="block max-w-[10rem] truncate text-sm font-bold text-ink-900">{{ $user->name }}</span><span class="block text-xs text-ink-600">{{ $user->role->value === 'developer' ? 'Developer' : 'Administrator' }}</span></span>
                </div>
            </div>
        </header>

        <main id="utama" class="px-4 pb-16 pt-6 sm:px-8 lg:pr-8">
            <div x-show="error" x-cloak class="mb-5 flex items-center gap-3 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#7F1D1D]" role="alert">
                <x-lucide-circle-alert class="h-5 w-5 shrink-0 text-[#DC2626]" aria-hidden="true" />
                <span class="flex-1" x-text="error"></span>
                <button type="button" @click="refresh()" class="rounded-lg border border-[#FECACA] bg-white px-3 py-1.5 text-xs font-bold text-[#B91C1C] hover:bg-[#FEE2E2]">Coba lagi</button>
            </div>

            {{ $slot }}
        </main>
    </div>

    @include('admin.partials.order-detail')

    {{-- Konfirmasi tolak --}}
    <x-modal show="rejectTarget !== null" close="rejectTarget = null" labelledby="r-title" width="w-[min(92vw,28rem)]">
        <template x-if="rejectTarget">
            <div class="p-6 sm:p-7">
                <span class="grid h-12 w-12 place-items-center rounded-2xl bg-[#FEE2E2] text-[#B91C1C]"><x-lucide-circle-x class="h-6 w-6" aria-hidden="true" /></span>
                <h2 id="r-title" class="mt-4 text-xl font-extrabold tracking-tight">Tolak orderan ini?</h2>
                <p class="mt-1.5 text-ink-600" x-text="`“${rejectTarget.project_title}” milik ${rejectTarget.contact_name}.`"></p>
                <p class="mt-3 rounded-xl bg-ink-50 p-3 text-sm text-ink-700">Status "Ditolak" tidak dapat diubah kembali. Alasan akan ditampilkan kepada klien di halaman orderannya.</p>
                <label for="r-reason" class="mt-4 block text-sm font-bold text-ink-800">Alasan penolakan <span class="font-normal text-ink-500">(opsional)</span></label>
                <textarea id="r-reason" rows="3" maxlength="500" x-model="rejectReason" placeholder="Contoh: Di luar cakupan layanan kami saat ini."
                          class="mt-1.5 w-full resize-none rounded-xl border-2 border-ink-200 px-3 py-2 text-sm text-ink-900 outline-none transition focus:border-brand-500"></textarea>
                <p class="mt-1 text-right text-xs text-ink-500" x-text="rejectReason.length + '/500'"></p>
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" @click="rejectTarget = null" class="inline-flex items-center justify-center rounded-xl border-2 border-ink-200 px-5 py-2.5 font-bold text-ink-800 transition hover:bg-ink-50">Batal</button>
                    <button type="button" @click="confirmReject()" class="inline-flex items-center justify-center rounded-xl bg-[#B91C1C] px-5 py-2.5 font-bold text-white transition hover:bg-[#991B1B]">Ya, Tolak Orderan</button>
                </div>
            </div>
        </template>
    </x-modal>
</div>
</x-layouts.base>
