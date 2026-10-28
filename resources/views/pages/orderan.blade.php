<x-layouts.site title="Orderan Saya">
<script>
document.addEventListener('alpine:init', () => {
    const FILTERS = [['all', 'Semua'], ['pending', 'Menunggu'], ['process', 'Dalam Proses'], ['approved', 'Disetujui'], ['rejected', 'Ditolak'], ['done', 'Selesai']];
    const CHIP = {
        pending: ['bg-[#FEF3C7] text-[#B45309]', 'Menunggu'], approved: ['bg-brand-100 text-brand-800', 'Disetujui'],
        process: ['bg-[#CFFAFE] text-[#0E7490]', 'Dalam Proses'], done: ['bg-[#DCFCE7] text-[#15803D]', 'Selesai'], rejected: ['bg-[#FEE2E2] text-[#B91C1C]', 'Ditolak'],
    };
    Alpine.data('orderList', (orders, user) => ({
        orders, filter: 'all', openId: null, payingId: null, FILTERS, CHIP, unread: {},
        init() {
            api('GET', '/api/chat/unread').then((r) => { this.unread = r.data ?? {}; }).catch(() => {});
            userChannel()?.listen('.chat.unread', (e) => { this.unread = { ...this.unread, [e.project_id]: e.unread }; });
            // Update langsung dari server (Laravel Reverb): status/harga orderan & pembayaran.
            const ch = userChannel();
            if (!ch) return;
            const toast = Alpine.store('toast');
            // Item diganti (bukan diubah di tempat) supaya semua binding x-for ikut dihitung ulang.
            const patch = (id, changes) => { this.orders = this.orders.map((x) => (x.id === id ? { ...x, ...changes } : x)); };
            ch.listen('.project.updated', (e) => {
                const o = this.orders.find((x) => x.id === e.id);
                if (!o) return;
                const statusChanged = o.status !== e.status;
                const priceChanged = (e.plan_price_range || '') !== o.finalPriceRaw;
                patch(e.id, {
                    status: e.status,
                    rejectReason: e.reject_reason || '',
                    finalPriceRaw: e.plan_price_range || '',
                    finalPrice: e.plan_price_range ? rp(Number(String(e.plan_price_range).replace(/\D/g, ''))) : '',
                });
                if (statusChanged) toast.show(`Status "${o.projectTitle}" kini: ${CHIP[e.status][1]}.`);
                else if (priceChanged) toast.show(`Harga final "${o.projectTitle}" telah ditetapkan.`);
            }).listen('.payment.changed', (e) => {
                const o = this.orders.find((x) => x.id === e.project_id);
                if (!o) return;
                if (e.status === 'paid' && !o.isPaid) { patch(o.id, { isPaid: true }); toast.show(`Pembayaran "${o.projectTitle}" diterima. Terima kasih!`); }
                else if (e.status === 'failed') toast.show(`Pembayaran "${o.projectTitle}" gagal atau kedaluwarsa.`, 'err');
            });
        },
        count(s) { return s === 'all' ? this.orders.length : this.orders.filter((o) => o.status === s).length; },
        get rows() { return this.filter === 'all' ? this.orders : this.orders.filter((o) => o.status === this.filter); },
        filterLabel(f) { return FILTERS.find((x) => x[0] === f)[1]; },
        canPay(o) { return !!o.finalPriceRaw && ['approved', 'process'].includes(o.status); },
        progress(o) { return { process: 50, done: 100 }[o.status]; },
        async pay(o) {
            this.payingId = o.id;
            const toast = Alpine.store('toast');
            try {
                await payProject(o.id, { customerName: o.contactName, customerEmail: user.email, customerPhone: o.contactPhone }, {
                    onSuccess: () => { toast.show('Pembayaran berhasil! Tim kami akan segera menghubungi Anda.'); setTimeout(() => location.reload(), 1200); },
                    onPending: () => toast.show('Pembayaran pending. Silakan selesaikan pembayaran Anda.'),
                    onError: () => toast.show('Pembayaran gagal. Silakan coba lagi.', 'err'),
                });
            } catch (e) { toast.show(e.message || 'Terjadi kesalahan saat memproses pembayaran', 'err'); }
            finally { this.payingId = null; }
        },
    }));
});
</script>

@php
    $statCards = [
        ['all', 'Total Orderan', 'layers', 'border-l-brand-600', 'bg-brand-100 text-brand-700'],
        ['pending', 'Menunggu', 'clock', 'border-l-[#D97706]', 'bg-[#FEF3C7] text-[#B45309]'],
        ['process', 'Dalam Proses', 'trending-up', 'border-l-[#0E7490]', 'bg-[#CFFAFE] text-[#0E7490]'],
        ['done', 'Selesai', 'circle-check', 'border-l-[#15803D]', 'bg-[#DCFCE7] text-[#15803D]'],
    ];
@endphp
<main class="relative overflow-hidden bg-ink-50" x-data="orderList(@js($orders), @js(['email' => auth()->user()->email]))">
    <div class="pointer-events-none absolute -left-24 top-0 h-72 w-72 animate-blob rounded-full bg-brand-100 opacity-70 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -right-24 top-64 h-72 w-72 animate-blob rounded-full bg-brand-200/50 blur-3xl" style="animation-delay: -7s" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-5xl px-5 pb-20 pt-10 sm:px-8 sm:pt-14">
        <div class="flex animate-rise flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">Orderan Saya</h1>
                <p class="mt-1.5 text-ink-600">Pantau status dan progress semua proyek Anda.</p>
            </div>
            <a href="{{ route('harga') }}" class="self-start px-5 py-3 text-sm {{ Ui::BTN_PRIMARY }}"><x-lucide-plus class="h-4 w-4" aria-hidden="true" /> Buat Orderan Baru</a>
        </div>

        {{-- Ringkasan --}}
        <section aria-label="Ringkasan orderan" class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($statCards as $i => [$key, $label, $icon, $border, $tile])
                <div class="animate-rise rounded-2xl border border-l-4 border-ink-200 bg-white p-5 shadow-soft {{ $border }}" style="animation-delay: {{ 60 * ($i + 1) }}ms">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-semibold text-ink-600">{{ $label }}</p>
                        <span class="grid h-10 w-10 place-items-center rounded-xl {{ $tile }}"><x-dynamic-component :component="'lucide-'.$icon" class="h-5 w-5" aria-hidden="true" /></span>
                    </div>
                    <p class="mt-2 text-3xl font-extrabold tabular-nums text-ink-900" x-data="countUp(count('{{ $key }}'))" x-text="val"></p>
                </div>
            @endforeach
        </section>

        {{-- Filter status --}}
        <div class="no-scrollbar -mx-5 mt-8 overflow-x-auto px-5 sm:mx-0 sm:px-0">
            <div class="flex w-max gap-2 sm:w-auto sm:flex-wrap" role="group" aria-label="Filter status orderan">
                <template x-for="[f, label] in FILTERS" :key="f">
                    <button type="button" :aria-pressed="f === filter" @click="filter = f"
                            class="inline-flex items-center gap-2 whitespace-nowrap rounded-full border px-4 py-2 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-400"
                            :class="f === filter ? 'border-brand-600 bg-brand-600 text-white shadow-soft' : 'border-ink-200 bg-white text-ink-700 hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700'">
                        <span x-text="label"></span>
                        <span class="grid min-w-[1.4rem] place-items-center rounded-full px-1.5 text-xs font-bold" :class="f === filter ? 'bg-white/20 text-white' : 'bg-ink-100 text-ink-700'" x-text="count(f)"></span>
                    </button>
                </template>
            </div>
        </div>

        <p class="sr-only" role="status" aria-live="polite" x-text="`Menampilkan ${rows.length} orderan`"></p>

        <div class="mt-5 space-y-4">
            {{-- kosong --}}
            <div x-show="rows.length === 0" class="rounded-2xl border border-ink-200 bg-white px-6 py-14 text-center shadow-soft">
                <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-brand-50 text-brand-600"><x-lucide-package-open class="h-8 w-8" aria-hidden="true" /></span>
                <h3 class="mt-5 text-lg font-extrabold text-ink-900" x-text="filter === 'all' ? 'Belum ada orderan' : `Tidak ada orderan berstatus ${filterLabel(filter)}`"></h3>
                <p class="mx-auto mt-1.5 max-w-sm text-ink-600" x-text="filter === 'all' ? 'Orderan Anda akan muncul di sini setelah Anda memesan paket.' : 'Coba pilih status lain untuk melihat orderan Anda.'"></p>
                <a x-show="filter === 'all'" href="{{ route('harga') }}" class="mt-6 px-5 py-3 text-sm {{ Ui::BTN_PRIMARY }}">Pilih Paket <x-lucide-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
                <button x-show="filter !== 'all'" type="button" @click="filter = 'all'" class="mt-6 px-5 py-2.5 text-sm {{ Ui::BTN_SECONDARY }}">Lihat Semua Orderan</button>
            </div>

            <template x-for="(o, i) in rows" :key="o.id">
                <article class="card-in rounded-2xl border bg-white p-5 shadow-soft transition duration-300 hover:border-brand-300 hover:shadow-lift sm:p-6"
                         :class="openId === o.id ? 'border-brand-300' : 'border-ink-200'" :style="`--d: ${i * 70}ms`">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-bold tracking-wide text-ink-600" x-text="'#' + o.id"></p>
                            <h3 class="mt-0.5 break-words text-lg font-extrabold text-ink-900" x-text="o.projectTitle"></h3>
                            <p class="mt-1 text-sm text-ink-600" x-text="(o.planTitle ? `Paket ${o.planTitle} · ` : '') + o.createdAt"></p>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold" :class="CHIP[o.status][0]" x-text="CHIP[o.status][1]"></span>
                    </div>

                    <div class="mt-4" x-show="progress(o) !== undefined">
                        <div class="mb-1.5 flex items-center justify-between text-sm"><span class="font-semibold text-ink-600">Progress</span><span class="font-extrabold text-ink-900" x-text="progress(o) + '%'"></span></div>
                        <div class="h-2.5 overflow-hidden rounded-full bg-ink-100" role="progressbar" :aria-valuenow="progress(o)" aria-valuemin="0" aria-valuemax="100" :aria-label="'Progress ' + o.projectTitle">
                            <div class="bar-grow h-full rounded-full" :class="o.status === 'done' ? 'bg-[#15803D]' : 'bg-gradient-to-r from-brand-600 to-brand-400'" :style="`width: ${progress(o)}%`"></div>
                        </div>
                    </div>

                    <p x-show="o.status === 'approved' && !o.isPaid" class="mt-3 flex items-center gap-2 text-sm text-ink-600">
                        <x-lucide-info class="h-4 w-4 shrink-0 text-brand-600" aria-hidden="true" />
                        <span x-text="canPay(o) ? 'Orderan disetujui. Selesaikan pembayaran agar proyek dapat segera dikerjakan.' : 'Orderan disetujui. Admin sedang menetapkan harga final proyek Anda.'"></span>
                    </p>
                    <p x-show="o.status === 'rejected'" class="mt-3 rounded-xl bg-[#FEE2E2] p-3 text-sm text-[#991B1B]">
                        <strong>Alasan penolakan:</strong> <span x-text="o.rejectReason || 'Admin tidak mencantumkan alasan. Hubungi kami lewat WhatsApp untuk informasi lebih lanjut.'"></span>
                    </p>
                    <p x-show="o.isPaid" class="mt-3 inline-flex items-center gap-2 rounded-full bg-[#DCFCE7] px-3 py-1 text-xs font-bold text-[#15803D]">
                        <x-lucide-circle-check class="h-3.5 w-3.5" aria-hidden="true" /> Pembayaran Lunas
                    </p>

                    <div x-show="openId === o.id" x-cloak class="step-in mt-5 grid gap-5 rounded-2xl bg-ink-50 p-5 text-sm sm:grid-cols-2">
                        <div class="space-y-4">
                            <h4 class="flex items-center gap-2 font-extrabold text-ink-900"><x-lucide-file-text class="h-4 w-4 text-brand-600" aria-hidden="true" /> Detail Proyek</h4>
                            <div><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Kategori</p><p class="mt-1 text-ink-900" x-text="o.category"></p></div>
                            <div><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Deskripsi</p><p class="mt-1 whitespace-pre-line break-words leading-relaxed text-ink-900" x-text="o.description"></p></div>
                            <div><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Skill Dibutuhkan</p><p class="mt-1 text-ink-900" x-text="o.skills"></p></div>
                            <div x-show="o.additionalNotes"><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Catatan Tambahan</p><p class="mt-1 whitespace-pre-line break-words text-ink-900" x-text="o.additionalNotes"></p></div>
                        </div>
                        <div class="space-y-4">
                            <h4 class="flex items-center gap-2 font-extrabold text-ink-900"><x-lucide-user class="h-4 w-4 text-brand-600" aria-hidden="true" /> Informasi Kontak</h4>
                            <div><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Nama Kontak</p><p class="mt-1 text-ink-900" x-text="o.contactName"></p></div>
                            <div><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Nomor Telepon</p><p class="mt-1 inline-flex items-center gap-1.5 text-ink-900"><x-lucide-phone class="h-3.5 w-3.5 text-ink-500" aria-hidden="true" /><span x-text="o.contactPhone"></span></p></div>
                            <div><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Target Selesai</p><p class="mt-1 inline-flex items-center gap-1.5 text-ink-900"><x-lucide-calendar class="h-3.5 w-3.5 text-ink-500" aria-hidden="true" /><span x-text="o.target"></span></p></div>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-col gap-3 border-t border-ink-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold text-ink-600" x-text="o.finalPriceRaw ? 'Harga final dari admin' : 'Estimasi biaya'"></p>
                            <p class="font-extrabold text-ink-900" x-text="o.finalPrice || o.estimateRange || 'Menunggu penetapan harga'"></p>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button type="button" @click="openId = openId === o.id ? null : o.id" :aria-expanded="openId === o.id" class="px-4 py-2.5 text-sm {{ Ui::BTN_SECONDARY }}">
                                <span x-text="openId === o.id ? 'Tutup Detail' : 'Lihat Detail'"></span>
                                <x-lucide-chevron-down class="h-4 w-4 transition-transform" ::class="openId === o.id ? 'rotate-180' : ''" aria-hidden="true" />
                            </button>
                            <a x-show="['approved', 'process', 'done'].includes(o.status)" :href="o.workspaceUrl" class="px-4 py-2.5 text-sm {{ Ui::BTN_SECONDARY }}"><x-lucide-layout-dashboard class="h-4 w-4" aria-hidden="true" /> Buka Workspace <span x-show="unread[o.id] > 0" x-cloak class="grid min-w-[1.25rem] place-items-center rounded-full bg-[#EF4444] px-1.5 text-[11px] font-extrabold leading-5 text-white" x-text="unread[o.id]" role="img" :aria-label="unread[o.id] + ' pesan chat belum dibaca'"></span></a>
                            <a x-show="o.isPaid" :href="o.invoiceUrl" class="px-4 py-2.5 text-sm {{ Ui::BTN_SECONDARY }}"><x-lucide-file-text class="h-4 w-4" aria-hidden="true" /> Lihat Invoice</a>
                            <button x-show="!o.isPaid && canPay(o)" type="button" @click="pay(o)" :disabled="payingId === o.id" class="px-4 py-2.5 text-sm {{ Ui::BTN_PRIMARY }}">
                                <x-button-spinner x-show="payingId === o.id" class="h-4 w-4" />
                                <x-lucide-credit-card x-show="payingId !== o.id" class="h-4 w-4" aria-hidden="true" />
                                <span x-text="payingId === o.id ? 'Memproses…' : 'Bayar Sekarang'"></span>
                            </button>
                            <a x-show="!o.isPaid && o.status === 'rejected'" href="{{ route('harga') }}" class="px-4 py-2.5 text-sm {{ Ui::BTN_SECONDARY }}">Ajukan Ulang</a>
                        </div>
                    </div>
                </article>
            </template>
        </div>
    </div>
</main>
</x-layouts.site>
