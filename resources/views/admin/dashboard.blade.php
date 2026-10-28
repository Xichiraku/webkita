<x-layouts.admin title="Dashboard" section="dashboard">
<div x-data="{ q: '', filter: 'all',
        get rows() {
            return orders.filter((o) => (this.filter === 'all' || o.status === this.filter)
                && matches(this.q, o.project_title, o.contact_name, catLabel(o.category), o.plan_title || '', o.contact_phone))
                .sort((a, b) => b.id - a.id).slice(0, 8);
        } }">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-brand-700">Selamat datang kembali, {{ auth()->user()->first_name ?: auth()->user()->username }}</p>
            <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">Dashboard Admin</h1>
            <p class="mt-1 text-ink-600">Ringkasan orderan, pengguna, dan pendapatan Webkita.</p>
        </div>
        <a href="{{ route('admin.orders') }}" class="card inline-flex items-center gap-2 rounded-2xl px-5 py-3 text-sm font-bold text-ink-800 transition hover:bg-white hover:text-brand-700">Kelola orderan <x-lucide-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
    </div>

    {{-- Ringkasan --}}
    <section aria-label="Ringkasan" class="admin-grid mt-6 grid grid-cols-1 gap-4 min-[560px]:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Total Orderan', 'package', 'orders.length', 'text-ink-900'],
            ['Perlu Tindakan', 'bell-ring', 'needAction', 'text-[#B45309]'],
            ['Pengguna', 'users', 'users.length', 'text-ink-900'],
            ['Pendapatan (Lunas)', 'wallet', 'rp(revenue)', 'text-[#15803D]'],
        ] as $i => [$label, $icon, $expr, $color])
            <div class="card fade-in rounded-3xl p-5" style="--d: {{ $i * 70 }}ms">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-semibold text-ink-600">{{ $label }}</p>
                    <span class="tile grid h-10 w-10 place-items-center rounded-xl text-brand-700"><x-dynamic-component :component="'lucide-'.$icon" class="h-5 w-5" aria-hidden="true" /></span>
                </div>
                <p class="mt-2 text-3xl font-extrabold tabular-nums {{ $color }}" x-text="loading ? '…' : {{ $expr }}"></p>
            </div>
        @endforeach
    </section>

    <div class="admin-grid mt-5 grid gap-5 xl:grid-cols-3">
        {{-- Alur orderan --}}
        <section aria-labelledby="t-funnel" class="card rounded-3xl p-6 xl:col-span-2">
            <h2 id="t-funnel" class="text-lg font-extrabold">Orderan per Status</h2>
            <ul class="mt-5 space-y-4">
                <template x-for="k in ['pending', 'approved', 'process', 'done', 'rejected']" :key="k">
                    <li>
                        <div class="mb-1.5 flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2 font-semibold text-ink-700"><span class="h-2.5 w-2.5 rounded-full" :style="`background: ${st(k).dot}`"></span><span x-text="st(k).label"></span></span>
                            <span class="font-extrabold tabular-nums text-ink-900" x-text="countStatus(k)"></span>
                        </div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-ink-100">
                            <div class="bar-grow h-full rounded-full" :style="`width: ${orders.length ? (countStatus(k) / orders.length * 100) : 0}%; background: ${st(k).dot}`"></div>
                        </div>
                    </li>
                </template>
            </ul>

            <h3 class="mt-8 text-sm font-extrabold uppercase tracking-wide text-ink-600">Orderan masuk 6 bulan terakhir</h3>
            <div class="mt-4 flex h-36 items-end gap-3" role="img" aria-label="Grafik orderan masuk per bulan">
                <template x-for="m in monthly()" :key="m.key">
                    <div class="flex h-full flex-1 flex-col items-center justify-end gap-1.5">
                        <span class="text-xs font-bold tabular-nums text-ink-700" x-text="m.n"></span>
                        <div class="w-full rounded-t-lg bg-gradient-to-t from-brand-600 to-brand-400 transition-all duration-700" :style="`height: ${Math.max(4, m.n / Math.max(1, ...monthly().map(x => x.n)) * 100)}%`"></div>
                        <span class="text-xs font-semibold text-ink-600" x-text="m.label"></span>
                    </div>
                </template>
            </div>
        </section>

        {{-- Kategori --}}
        <section aria-labelledby="t-cat" class="card rounded-3xl p-6">
            <h2 id="t-cat" class="text-lg font-extrabold">Kategori Layanan</h2>
            <ul class="mt-5 space-y-3">
                <template x-for="c in categoryStats()" :key="c.key">
                    <li class="flex items-center justify-between gap-3 rounded-2xl bg-ink-50 px-4 py-3 text-sm">
                        <span class="font-semibold text-ink-800" x-text="c.label"></span>
                        <span class="font-extrabold tabular-nums text-brand-700" x-text="c.n"></span>
                    </li>
                </template>
                <li x-show="!loading && !orders.length" class="text-sm text-ink-600">Belum ada data.</li>
            </ul>
        </section>
    </div>

    {{-- Perlu tindakan --}}
    <section id="aksi" aria-labelledby="t-aksi" class="card mt-5 rounded-3xl p-6">
        <div class="flex items-center justify-between gap-3">
            <h2 id="t-aksi" class="text-lg font-extrabold">Perlu Tindakan</h2>
            <span class="rounded-full bg-brand-100 px-3 py-1 text-xs font-bold text-brand-800" x-text="needAction + ' orderan'"></span>
        </div>
        <ul class="mt-4 grid gap-3 md:grid-cols-2">
            <template x-for="o in actionList" :key="o.id">
                <li class="flex items-center justify-between gap-3 rounded-2xl bg-ink-50 p-4">
                    <div class="min-w-0">
                        <p class="truncate font-bold text-ink-900" x-text="o.project_title"></p>
                        <p class="truncate text-xs text-ink-600"><span x-text="o.contact_name"></span> · <span x-text="o.status === 'pending' ? 'Menunggu persetujuan' : (priceOf(o) ? 'Menunggu pembayaran' : 'Tetapkan harga')"></span></p>
                    </div>
                    <button type="button" @click="detail = o" class="shrink-0 rounded-xl border-2 border-ink-200 bg-white px-3.5 py-2 text-sm font-bold text-ink-800 transition hover:border-brand-400 hover:text-brand-700">Tinjau</button>
                </li>
            </template>
            <li x-show="!loading && !actionList.length" class="text-sm text-ink-600">Tidak ada orderan yang perlu ditindaklanjuti. 🎉</li>
        </ul>
    </section>

    {{-- Orderan terbaru --}}
    <section aria-labelledby="orders-title" class="mt-5" id="orderan">
        <div class="card overflow-hidden rounded-3xl">
            <div class="flex flex-wrap items-center justify-between gap-3 p-5 sm:p-6">
                <h2 id="orders-title" class="text-lg font-extrabold">Orderan Terbaru</h2>
                <div class="relative w-full sm:w-72">
                    <label for="search" class="sr-only">Cari orderan</label>
                    <x-lucide-search class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-ink-500" aria-hidden="true" />
                    <input id="search" type="search" x-model.debounce.200ms="q" placeholder="Cari proyek atau nama…" autocomplete="off" class="{{ Ui::A_INPUT }} pl-10 pr-3">
                </div>
            </div>
            <p x-show="loading" class="px-6 pb-10 text-center text-sm font-semibold text-ink-600" role="status">Memuat data…</p>
            <p x-show="!loading && !rows.length" x-cloak class="px-6 pb-10 text-center text-ink-600">Tidak ada orderan yang cocok.</p>
            <div x-show="!loading && rows.length" x-cloak>@include('admin.partials.orders-table', ['src' => 'rows'])</div>
        </div>
    </section>
</div>
</x-layouts.admin>
