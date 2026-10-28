{{-- Tabel orderan. Param `src` = ekspresi Alpine untuk daftar baris (default: rows). --}}
@php($src = $src ?? 'rows')
<div class="overflow-x-auto">
    <table class="w-full min-w-[960px] border-collapse text-sm">
        <thead>
            <tr class="border-b border-ink-100 text-left text-xs font-bold uppercase tracking-wider text-ink-500">
                <th scope="col" class="px-5 py-3 sm:pl-6">Orderan</th>
                <th scope="col" class="px-3 py-3">Klien</th>
                <th scope="col" class="px-3 py-3">Paket</th>
                <th scope="col" class="px-3 py-3">Developer</th>
                <th scope="col" class="px-3 py-3">Harga</th>
                <th scope="col" class="px-3 py-3">Tanggal</th>
                <th scope="col" class="px-3 py-3">Status</th>
                <th scope="col" class="px-3 py-3 pr-5 text-right sm:pr-6"><span class="sr-only">Aksi</span></th>
            </tr>
        </thead>
        <tbody>
            <template x-for="(o, i) in {{ $src }}" :key="o.id">
                <tr class="row-in border-b border-ink-100 align-middle last:border-0 hover:bg-brand-50/40" :style="`--d: ${i * 35}ms`" x-data="{ editPrice: false, price: '', saving: false }">
                    <td class="px-5 py-3.5 sm:pl-6">
                        <p class="max-w-[16rem] truncate font-bold text-ink-900" x-text="o.project_title" :title="o.project_title"></p>
                        <p class="text-xs text-ink-600"><span x-text="'#' + o.id"></span> · <span x-text="catLabel(o.category)"></span></p>
                    </td>
                    <td class="px-3 py-3.5">
                        <p class="font-semibold text-ink-900" x-text="o.contact_name"></p>
                        <a :href="waLink(o.contact_phone)" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold text-brand-700 hover:underline" x-text="o.contact_phone"></a>
                    </td>
                    <td class="px-3 py-3.5 text-ink-700" x-text="o.plan_title || '–'"></td>
                    <td class="px-3 py-3.5 text-ink-700" x-text="developerName(o) || '–'"></td>
                    <td class="px-3 py-3.5">
                        <template x-if="priceOf(o)"><span class="font-bold text-ink-900" x-text="rp(priceOf(o))"></span></template>
                        <template x-if="!priceOf(o)">
                            <div>
                                <button type="button" x-show="!editPrice && o.status !== 'rejected'" @click="editPrice = true; $nextTick(() => $refs.p.focus())" class="rounded-lg border border-dashed border-brand-400 px-2.5 py-1 text-xs font-bold text-brand-700 hover:bg-brand-50">Tetapkan harga</button>
                                <span x-show="o.status === 'rejected'" class="text-ink-500">–</span>
                                <form x-show="editPrice" x-cloak class="flex items-center gap-1.5"
                                      @submit.prevent="saving = true; setPrice(o, price).then(ok => { saving = false; if (ok) editPrice = false })"
                                      @keydown.escape.stop="editPrice = false">
                                    <label class="sr-only" :for="'price-' + o.id">Harga final</label>
                                    <input :id="'price-' + o.id" x-ref="p" x-model="price" inputmode="numeric" placeholder="3000000" class="h-8 w-28 rounded-lg border border-ink-200 px-2 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-300">
                                    <button type="submit" :disabled="saving" class="grid h-8 w-8 place-items-center rounded-lg bg-brand-600 text-white hover:bg-brand-700 disabled:opacity-60" aria-label="Simpan harga"><x-lucide-check class="h-4 w-4" aria-hidden="true" /></button>
                                    <button type="button" @click="editPrice = false" class="grid h-8 w-8 place-items-center rounded-lg border border-ink-200 text-ink-700 hover:bg-ink-50" aria-label="Batal"><x-lucide-x class="h-4 w-4" aria-hidden="true" /></button>
                                </form>
                            </div>
                        </template>
                    </td>
                    <td class="whitespace-nowrap px-3 py-3.5 text-ink-700" x-text="fmtDate(o.created_at)"></td>
                    <td class="px-3 py-3.5">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 shrink-0 rounded-full" :style="`background: ${st(o.status).dot}`" aria-hidden="true"></span>
                            <label class="sr-only" :for="'st-' + o.id">Status orderan</label>
                            <select :id="'st-' + o.id" :disabled="busyId === o.id || NEXT[o.status].length < 2" :value="o.status" @change="pickStatus(o, $event.target.value); $event.target.value = o.status"
                                    class="h-9 cursor-pointer rounded-lg border border-ink-200 bg-white/80 px-2.5 text-sm font-semibold text-ink-800 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-300 disabled:cursor-not-allowed disabled:opacity-70">
                                <template x-for="k in NEXT[o.status]" :key="k"><option :value="k" :selected="k === o.status" x-text="st(k).label"></option></template>
                            </select>
                        </div>
                    </td>
                    <td class="px-3 py-3.5 pr-5 text-right sm:pr-6">
                        <button type="button" @click="detail = o" class="inline-grid h-9 w-9 place-items-center rounded-lg border border-ink-200 bg-white/80 text-ink-700 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700" :aria-label="'Lihat detail ' + o.project_title"><x-lucide-eye class="h-4 w-4" aria-hidden="true" /></button>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</div>
