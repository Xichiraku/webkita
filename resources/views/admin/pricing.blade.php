<x-layouts.admin title="Paket Layanan" section="paket">
<div x-data="{ open: false, form: null, saving: false,
        newPlan() { this.form = blankPlan(); this.open = true; },
        edit(p) { this.form = editPlan(p); this.open = true; },
        async save() { this.saving = true; const ok = await savePlan(this.form); this.saving = false; if (ok) this.open = false; } }">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-brand-700">Admin</p>
            <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">Paket Layanan</h1>
            <p class="mt-1 text-ink-600">Kelola paket harga yang tampil di halaman Harga.</p>
        </div>
        <button type="button" @click="newPlan()" class="inline-flex items-center gap-2 rounded-2xl bg-brand-600 px-5 py-3 text-sm font-bold text-white shadow-soft transition hover:bg-brand-700"><x-lucide-plus class="h-4 w-4" aria-hidden="true" /> Paket Baru</button>
    </div>

    <p x-show="loading" class="mt-10 text-center text-sm font-semibold text-ink-600" role="status">Memuat data…</p>
    <p x-show="!loading && !plans.length" x-cloak class="card mt-6 rounded-3xl p-10 text-center text-ink-600">Belum ada paket layanan.</p>

    <section aria-label="Daftar paket" class="admin-grid mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
        <template x-for="(p, i) in plans" :key="p.id">
            <article class="card flex flex-col rounded-3xl p-5" :class="p.is_active ? '' : 'opacity-70'">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-ink-500" x-text="'Urutan ' + p.sort_order + ' · ' + p.tier"></p>
                        <h2 class="text-xl font-extrabold text-ink-900" x-text="p.title"></h2>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="p.is_active ? 'bg-[#DCFCE7] text-[#15803D]' : 'bg-ink-100 text-ink-700'" x-text="p.is_active ? 'Aktif' : 'Nonaktif'"></span>
                </div>
                <p class="mt-1 text-sm font-semibold text-ink-600" x-text="p.price_range"></p>
                <p class="mt-1 text-xs text-ink-500" x-show="p.timeline" x-text="p.timeline"></p>
                <ul class="mt-4 flex-1 space-y-1.5 text-sm text-ink-700">
                    <template x-for="f in p.features.slice(0, 4)" :key="f.id"><li class="flex items-start gap-2"><x-lucide-check class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" aria-hidden="true" /><span x-text="f.feature"></span></li></template>
                    <li x-show="p.features.length > 4" class="text-xs text-ink-500" x-text="`+${p.features.length - 4} fitur lainnya`"></li>
                </ul>
                <p class="mt-3 text-xs text-ink-500" x-text="planUsage(p) + ' orderan memakai paket ini'"></p>
                <div class="mt-4 flex items-center gap-2 border-t border-ink-100 pt-4">
                    <button type="button" @click="edit(p)" class="flex-1 rounded-xl border-2 border-ink-200 bg-white px-3 py-2 text-sm font-bold text-ink-800 transition hover:border-brand-400 hover:text-brand-700">Ubah</button>
                    <button type="button" @click="togglePlan(p)" class="rounded-xl border-2 border-ink-200 bg-white px-3 py-2 text-sm font-bold text-ink-800 transition hover:bg-ink-50" x-text="p.is_active ? 'Nonaktifkan' : 'Aktifkan'"></button>
                    <button type="button" @click="deletePlan(p)" class="grid h-10 w-10 place-items-center rounded-xl border-2 border-ink-200 bg-white text-ink-700 transition hover:border-[#FECACA] hover:bg-[#FEE2E2] hover:text-[#B91C1C]" :aria-label="'Hapus paket ' + p.title"><x-lucide-trash-2 class="h-4 w-4" aria-hidden="true" /></button>
                </div>
            </article>
        </template>
    </section>

    {{-- Form paket --}}
    <x-modal show="open" labelledby="plan-title" width="w-[min(94vw,42rem)]">
        <template x-if="form">
            <form @submit.prevent="save()" class="p-6 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <h2 id="plan-title" class="text-2xl font-extrabold tracking-tight" x-text="form.id ? 'Ubah Paket' : 'Paket Baru'"></h2>
                    <button type="button" @click="open = false" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-ink-600 hover:bg-ink-100" aria-label="Tutup"><x-lucide-x class="h-5 w-5" aria-hidden="true" /></button>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div><label for="p-title" class="{{ Ui::LABEL }}">Nama paket</label><input id="p-title" x-model="form.title" required class="{{ Ui::A_INPUT }} px-3"></div>
                    <div><label for="p-tier" class="{{ Ui::LABEL }}">Kode tier</label><input id="p-tier" x-model="form.tier" required placeholder="starter" class="{{ Ui::A_INPUT }} px-3"></div>
                    <div><label for="p-min" class="{{ Ui::LABEL }}">Harga minimum (Rp)</label><input id="p-min" x-model="form.min_price" required inputmode="numeric" class="{{ Ui::A_INPUT }} px-3"></div>
                    <div><label for="p-max" class="{{ Ui::LABEL }}">Harga maksimum (Rp) <span class="font-medium text-ink-500">(kosong = tanpa batas)</span></label><input id="p-max" x-model="form.max_price" inputmode="numeric" class="{{ Ui::A_INPUT }} px-3"></div>
                    <div class="sm:col-span-2"><label for="p-range" class="{{ Ui::LABEL }}">Teks rentang harga <span class="font-medium text-ink-500">(kosong = otomatis)</span></label><input id="p-range" x-model="form.price_range" placeholder="Rp 500.000 – Rp 1.500.000" class="{{ Ui::A_INPUT }} px-3"></div>
                    <div><label for="p-time" class="{{ Ui::LABEL }}">Estimasi waktu</label><input id="p-time" x-model="form.timeline" placeholder="1–2 minggu" class="{{ Ui::A_INPUT }} px-3"></div>
                    <div><label for="p-badge" class="{{ Ui::LABEL }}">Lencana</label><input id="p-badge" x-model="form.badge" placeholder="Paling populer" class="{{ Ui::A_INPUT }} px-3"></div>
                    <div class="sm:col-span-2"><label for="p-desc" class="{{ Ui::LABEL }}">Deskripsi</label><textarea id="p-desc" x-model="form.description" rows="2" class="w-full rounded-xl border border-ink-200 bg-white/80 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-300"></textarea></div>
                    <div class="sm:col-span-2"><label for="p-up" class="{{ Ui::LABEL }}">Catatan upgrade</label><input id="p-up" x-model="form.upgrade_note" class="{{ Ui::A_INPUT }} px-3"></div>
                    <div><label for="p-sort" class="{{ Ui::LABEL }}">Urutan tampil</label><input id="p-sort" type="number" x-model="form.sort_order" class="{{ Ui::A_INPUT }} px-3"></div>
                    <label class="flex items-center gap-2.5 self-end pb-3 text-sm font-bold text-ink-800"><input type="checkbox" x-model="form.is_active" class="h-5 w-5 rounded border-ink-400 accent-brand-600"> Paket aktif</label>
                </div>

                @foreach ([['features', 'Fitur yang termasuk'], ['not_included', 'Tidak termasuk']] as [$key, $title])
                    <fieldset class="mt-6">
                        <legend class="text-sm font-extrabold text-ink-800">{{ $title }}</legend>
                        <div class="mt-2 space-y-2">
                            <template x-for="(line, idx) in form.{{ $key }}" :key="idx">
                                <div class="flex gap-2">
                                    <input x-model="form.{{ $key }}[idx]" class="{{ Ui::A_INPUT }} px-3" :aria-label="'{{ $title }} ' + (idx + 1)">
                                    <button type="button" @click="form.{{ $key }}.splice(idx, 1)" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-ink-200 text-ink-700 hover:bg-[#FEE2E2] hover:text-[#B91C1C]" aria-label="Hapus baris"><x-lucide-trash-2 class="h-4 w-4" aria-hidden="true" /></button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="form.{{ $key }}.push('')" class="mt-2 inline-flex items-center gap-1.5 text-sm font-bold text-brand-700 hover:underline"><x-lucide-plus class="h-4 w-4" aria-hidden="true" /> Tambah baris</button>
                    </fieldset>
                @endforeach

                <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" @click="open = false" class="inline-flex items-center justify-center rounded-xl border-2 border-ink-200 px-5 py-2.5 font-bold text-ink-800 hover:bg-ink-50">Batal</button>
                    <button type="submit" :disabled="saving" class="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-2.5 font-bold text-white hover:bg-brand-700 disabled:opacity-70">
                        <x-button-spinner x-show="saving" class="h-4 w-4" /> <span x-text="saving ? 'Menyimpan…' : 'Simpan Paket'"></span>
                    </button>
                </div>
            </form>
        </template>
    </x-modal>
</div>
</x-layouts.admin>
