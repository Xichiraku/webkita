<x-layouts.admin title="Orderan" section="orderan">
<div x-data="{ filter: 'all', q: '', cat: '', pkg: '', sort: 'new', page: 1,
        get rows() {
            const r = orders.filter((o) => (this.filter === 'all' || o.status === this.filter)
                && (!this.cat || o.category === this.cat) && (!this.pkg || (o.plan_title || '') === this.pkg)
                && matches(this.q, o.project_title, o.contact_name, o.contact_phone, catLabel(o.category), o.plan_title || ''));
            const by = { new: (a, b) => b.id - a.id, old: (a, b) => a.id - b.id, price: (a, b) => priceOf(b) - priceOf(a) };
            return r.sort(by[this.sort]);
        },
        get pg() { return paginate(this.rows, this.page); },
        get anyFilter() { return this.filter !== 'all' || this.cat || this.pkg || this.q; },
        reset() { this.filter = 'all'; this.cat = ''; this.pkg = ''; this.q = ''; this.page = 1; } }"
     x-effect="rows; page = Math.min(page, Math.max(1, Math.ceil(rows.length / PER_PAGE)))">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-brand-700">Admin</p>
            <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">Manajemen Orderan</h1>
            <p class="mt-1 text-ink-600">Setujui, tolak, tetapkan harga, dan pantau seluruh orderan klien.</p>
        </div>
    </div>

    <section aria-label="Daftar orderan" class="mt-6">
        <div class="card overflow-hidden rounded-3xl">
            <div class="space-y-4 p-5 sm:p-6">
                <div class="grid gap-3 md:grid-cols-[1fr_12rem_10rem_10rem]">
                    <div class="relative">
                        <label for="search" class="sr-only">Cari orderan</label>
                        <x-lucide-search class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-ink-500" aria-hidden="true" />
                        <input id="search" type="search" x-model.debounce.200ms="q" @input="page = 1" placeholder="Cari proyek, nama, atau telepon…" autocomplete="off" class="{{ Ui::A_INPUT }} pl-10 pr-3">
                    </div>
                    <select x-model="cat" @change="page = 1" aria-label="Filter kategori" class="{{ Ui::A_SELECT }}">
                        <option value="">Semua kategori</option>
                        @foreach (Ui::CATEGORIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                    </select>
                    <select x-model="pkg" @change="page = 1" aria-label="Filter paket" class="{{ Ui::A_SELECT }}">
                        <option value="">Semua paket</option>
                        <template x-for="p in plans" :key="p.id"><option :value="p.title" x-text="p.title"></option></template>
                    </select>
                    <select x-model="sort" aria-label="Urutkan" class="{{ Ui::A_SELECT }}">
                        <option value="new">Terbaru</option><option value="old">Terlama</option><option value="price">Harga tertinggi</option>
                    </select>
                </div>

                <div class="no-scrollbar -mx-1 overflow-x-auto px-1">
                    <div class="flex w-max gap-2" role="group" aria-label="Filter status">
                        <template x-for="k in ['all', 'pending', 'approved', 'process', 'done', 'rejected']" :key="k">
                            <button type="button" :aria-pressed="k === filter" @click="filter = k; page = 1"
                                    class="inline-flex items-center gap-2 whitespace-nowrap rounded-lg border px-3.5 py-2 text-sm font-semibold transition"
                                    :class="k === filter ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-ink-200 bg-white/80 text-ink-700 hover:border-brand-300 hover:bg-brand-50'">
                                <span x-text="k === 'all' ? 'Semua' : st(k).label"></span>
                                <span class="text-xs font-bold" :class="k === filter ? 'text-brand-700' : 'text-ink-600'" x-text="countStatus(k)"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div x-show="loading" class="px-6 pb-10 text-center text-sm font-semibold text-ink-600" role="status">Memuat data…</div>

            <div x-show="!loading && rows.length === 0" x-cloak class="px-6 pb-12 text-center">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600"><x-lucide-package-open class="h-7 w-7" aria-hidden="true" /></span>
                <h2 class="mt-4 text-lg font-extrabold">Tidak ada orderan</h2>
                <p class="mt-1 text-ink-600" x-text="orders.length ? 'Coba ubah kata kunci atau filter.' : 'Belum ada orderan masuk.'"></p>
                <button type="button" x-show="anyFilter" @click="reset()" class="mt-4 inline-flex items-center gap-2 rounded-xl border-2 border-ink-200 px-4 py-2 text-sm font-bold text-ink-800 hover:bg-ink-50">Reset filter</button>
            </div>

            <div x-show="!loading && rows.length > 0" x-cloak>
                @include('admin.partials.orders-table', ['src' => 'pg.items'])
                @include('admin.partials.pager')
            </div>
        </div>
    </section>
</div>
</x-layouts.admin>
