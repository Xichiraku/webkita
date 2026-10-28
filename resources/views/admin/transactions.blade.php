<x-layouts.admin title="Transaksi" section="transaksi">
<div x-data="{ q: '', filter: 'semua', sort: 'new', page: 1,
        sum(k) { return trx.filter((t) => t.status === k).reduce((s, t) => s + Number(t.amount || 0), 0); },
        cnt(k) { return k === 'semua' ? trx.length : trx.filter((t) => t.status === k).length; },
        projectTitle(id) { return orders.find((o) => o.id === id)?.project_title; },
        get rows() {
            const r = trx.filter((t) => (this.filter === 'semua' || t.status === this.filter)
                && matches(this.q, t.order_id, String(t.project_id), this.projectTitle(t.project_id) || ''));
            return r.sort(this.sort === 'amount' ? (a, b) => b.amount - a.amount : (a, b) => b.id - a.id);
        },
        get pg() { return paginate(this.rows, this.page); } }">
    <div>
        <p class="text-sm font-semibold text-brand-700">Admin</p>
        <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">Transaksi</h1>
        <p class="mt-1 text-ink-600">Pembayaran proyek lewat Midtrans.</p>
    </div>

    <section aria-label="Ringkasan transaksi" class="admin-grid mt-6 grid grid-cols-1 gap-4 min-[560px]:grid-cols-2 xl:grid-cols-4">
        <template x-for="k in ['paid', 'pending', 'failed', 'challenge']" :key="k">
            <button type="button" @click="filter = filter === k ? 'semua' : k; page = 1" :aria-pressed="filter === k"
                    class="card rounded-3xl p-5 text-left transition hover:bg-white" :class="filter === k ? 'ring-2 ring-brand-400' : ''">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" :class="tx(k).chip" x-text="tx(k).label"></span>
                <p class="mt-3 text-2xl font-extrabold tabular-nums text-ink-900" x-text="rp(sum(k))"></p>
                <p class="text-sm text-ink-600" x-text="cnt(k) + ' transaksi'"></p>
            </button>
        </template>
    </section>

    <section aria-label="Daftar transaksi" class="mt-5">
        <div class="card overflow-hidden rounded-3xl">
            <div class="grid gap-3 p-5 sm:p-6 md:grid-cols-[1fr_12rem]">
                <div class="relative">
                    <label for="search" class="sr-only">Cari transaksi</label>
                    <x-lucide-search class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-ink-500" aria-hidden="true" />
                    <input id="search" type="search" x-model.debounce.200ms="q" @input="page = 1" placeholder="Cari order ID atau project…" autocomplete="off" class="{{ Ui::A_INPUT }} pl-10 pr-3">
                </div>
                <select x-model="sort" aria-label="Urutkan" class="{{ Ui::A_SELECT }}"><option value="new">Terbaru</option><option value="amount">Nominal tertinggi</option></select>
            </div>

            <p x-show="loading" class="px-6 pb-10 text-center text-sm font-semibold text-ink-600" role="status">Memuat data…</p>
            <div x-show="!loading && !rows.length" x-cloak class="px-6 pb-12 text-center">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600"><x-lucide-receipt class="h-7 w-7" aria-hidden="true" /></span>
                <p class="mt-3 text-ink-600" x-text="trx.length ? 'Coba ubah kata kunci atau filter status.' : 'Belum ada transaksi yang tercatat.'"></p>
            </div>

            <div x-show="!loading && rows.length" x-cloak>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-ink-100 text-left text-xs font-bold uppercase tracking-wider text-ink-500">
                                <th scope="col" class="px-5 py-3 sm:pl-6">Order ID</th><th scope="col" class="px-3 py-3">Proyek</th><th scope="col" class="px-3 py-3">Nominal</th>
                                <th scope="col" class="px-3 py-3">Metode</th><th scope="col" class="px-3 py-3">Tanggal</th><th scope="col" class="px-3 py-3 pr-5 sm:pr-6">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(t, i) in pg.items" :key="t.id">
                                <tr class="row-in border-b border-ink-100 last:border-0 hover:bg-brand-50/40" :style="`--d: ${i * 35}ms`">
                                    <td class="px-5 py-3.5 font-mono text-xs font-semibold text-ink-800 sm:pl-6" x-text="t.order_id"></td>
                                    <td class="px-3 py-3.5"><p class="max-w-[14rem] truncate font-semibold text-ink-900" x-text="projectTitle(t.project_id) || '–'"></p><p class="text-xs text-ink-600" x-text="'#' + t.project_id"></p></td>
                                    <td class="whitespace-nowrap px-3 py-3.5 font-bold text-ink-900" x-text="rp(t.amount)"></td>
                                    <td class="px-3 py-3.5 text-ink-700" x-text="t.payment_type ? t.payment_type.replace(/_/g, ' ') : '–'"></td>
                                    <td class="whitespace-nowrap px-3 py-3.5 text-ink-700" x-text="fmtDate(t.created_at)"></td>
                                    <td class="px-3 py-3.5 pr-5 sm:pr-6"><span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold" :class="tx(t.status).chip" x-text="tx(t.status).label"></span></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                @include('admin.partials.pager')
            </div>
        </div>
    </section>
</div>
</x-layouts.admin>
