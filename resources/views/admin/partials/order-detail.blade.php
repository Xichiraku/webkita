{{-- Modal detail orderan (dibaca dari scope adminApp: detail) --}}
<x-modal show="detail !== null" close="detail = null" labelledby="d-title" width="w-[min(94vw,44rem)]">
    <template x-if="detail">
        <div class="p-6 sm:p-8">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs font-bold tracking-wide text-ink-600" x-text="'ORDERAN #' + detail.id"></p>
                    <h2 id="d-title" class="mt-0.5 break-words text-2xl font-extrabold tracking-tight" x-text="detail.project_title"></h2>
                    <p class="mt-1 text-sm text-ink-600" x-text="(detail.plan_title ? `Paket ${detail.plan_title} · ` : '') + fmtDate(detail.created_at)"></p>
                </div>
                <button type="button" @click="detail = null" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-ink-600 transition hover:bg-ink-100" aria-label="Tutup detail"><x-lucide-x class="h-5 w-5" aria-hidden="true" /></button>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold" :class="st(detail.status).chip" x-text="st(detail.status).label"></span>
                <span class="text-sm font-bold text-ink-900" x-text="priceOf(detail) ? rp(priceOf(detail)) : (detail.price_range || 'Harga belum ditetapkan')"></span>
            </div>

            {{-- Penugasan developer (admin) --}}
            <div x-show="canAssign(detail)" x-data="{ choice: '' }" x-effect="choice = String(detail.developer_id ?? '')" class="mt-5 rounded-2xl border border-ink-200 bg-ink-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-ink-600">Developer proyek</p>
                <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                    <label class="sr-only" for="dev-select">Pilih developer</label>
                    <select id="dev-select" x-model="choice" class="h-11 min-w-0 flex-1 cursor-pointer rounded-xl border border-ink-200 bg-white px-3 text-sm font-semibold text-ink-800 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-300">
                        <option value="">Belum ditugaskan</option>
                        <template x-for="u in staff" :key="u.id"><option :value="String(u.id)" x-text="userName(u) + (u.id === meId ? ' (saya)' : '')"></option></template>
                    </select>
                    <button type="button" @click="assignDeveloper(detail, choice)" class="inline-flex h-11 items-center justify-center rounded-xl bg-brand-600 px-4 text-sm font-bold text-white transition hover:bg-brand-700">Simpan</button>
                    <button type="button" x-show="detail.developer_id !== meId" @click="choice = String(meId); assignDeveloper(detail, meId)" class="inline-flex h-11 items-center justify-center rounded-xl border-2 border-ink-200 bg-white px-4 text-sm font-bold text-ink-800 transition hover:border-brand-400 hover:text-brand-700">Ambil proyek</button>
                </div>
            </div>
            <p x-show="!canAssign(detail) && detail.developer_id" class="mt-5 text-sm text-ink-700">Developer: <strong x-text="developerName(detail)"></strong></p>

            <p x-show="detail.status === 'rejected'" class="mt-4 rounded-xl bg-[#FEE2E2] p-3 text-sm text-[#991B1B]"><strong>Alasan penolakan:</strong> <span x-text="detail.reject_reason || 'Tidak dicantumkan.'"></span></p>

            <div class="mt-6 grid gap-5 rounded-2xl bg-ink-50 p-5 text-sm sm:grid-cols-2">
                <div class="space-y-4">
                    <div><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Kategori</p><p class="mt-1 text-ink-900" x-text="catLabel(detail.category)"></p></div>
                    <div><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Deskripsi</p><p class="mt-1 whitespace-pre-line break-words leading-relaxed text-ink-900" x-text="detail.description"></p></div>
                    <div><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Skill Dibutuhkan</p><p class="mt-1 text-ink-900" x-text="detail.skills"></p></div>
                    <div x-show="detail.additional_notes"><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Catatan</p><p class="mt-1 whitespace-pre-line break-words text-ink-900" x-text="detail.additional_notes"></p></div>
                </div>
                <div class="space-y-4">
                    <div><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Nama Kontak</p><p class="mt-1 text-ink-900" x-text="detail.contact_name"></p></div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-ink-600">Nomor WhatsApp</p>
                        <a :href="waLink(detail.contact_phone)" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1.5 font-semibold text-brand-700 hover:underline"><x-lucide-message-circle class="h-4 w-4" aria-hidden="true" /><span x-text="detail.contact_phone"></span></a>
                    </div>
                    <div x-data="{ files: null }" x-init="api('GET', `/api/attachments/${detail.id}`).then(r => files = r.data).catch(() => files = [])">
                        <p class="text-xs font-bold uppercase tracking-wide text-ink-600">Lampiran</p>
                        <p x-show="files === null" class="mt-1 text-ink-500">Memuat…</p>
                        <p x-show="files && !files.length" class="mt-1 text-ink-500">Tidak ada lampiran.</p>
                        <ul class="mt-1 space-y-1.5">
                            <template x-for="f in files || []" :key="f.id">
                                <li><a :href="`/api/attachments/download/${f.id}`" class="inline-flex items-center gap-1.5 break-all font-semibold text-brand-700 hover:underline"><x-lucide-paperclip class="h-4 w-4 shrink-0" aria-hidden="true" /><span x-text="f.file_name"></span></a></li>
                            </template>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="button" @click="detail = null" class="inline-flex items-center justify-center rounded-xl border-2 border-ink-200 px-5 py-2.5 font-bold text-ink-800 transition hover:bg-ink-50">Tutup</button>
                <template x-if="detail.status === 'pending'">
                    <div class="flex flex-col-reverse gap-3 sm:flex-row">
                        <button type="button" @click="const o = detail; detail = null; pickStatus(o, 'rejected')" class="inline-flex items-center justify-center rounded-xl border-2 border-[#FECACA] px-5 py-2.5 font-bold text-[#B91C1C] transition hover:bg-[#FEE2E2]">Tolak</button>
                        <button type="button" @click="const o = detail; detail = null; setStatus(o, 'approved')" class="inline-flex items-center justify-center rounded-xl bg-brand-600 px-5 py-2.5 font-bold text-white transition hover:bg-brand-700">Setujui</button>
                    </div>
                </template>
            </div>
        </div>
    </template>
</x-modal>
