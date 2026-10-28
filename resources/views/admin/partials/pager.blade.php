{{-- Paginasi. Butuh `pg` (hasil paginate()) dan `page` pada scope Alpine induk. --}}
<div class="flex flex-col items-center justify-between gap-3 border-t border-ink-100 px-5 py-4 sm:flex-row sm:px-6">
    <p class="text-sm text-ink-600" x-text="pg.info"></p>
    <nav x-show="pg.pages > 1" class="flex flex-wrap items-center gap-1.5" aria-label="Halaman">
        <button type="button" :disabled="pg.page <= 1" @click="page = pg.page - 1" class="grid h-9 min-w-[2.25rem] place-items-center rounded-lg border border-ink-200 bg-white/80 px-2 text-sm font-bold text-ink-700 transition hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Halaman sebelumnya"><x-lucide-chevron-left class="h-4 w-4" aria-hidden="true" /></button>
        <template x-for="(n, i) in pg.nums" :key="i">
            <span>
                <span x-show="n === '…'" class="px-1 text-sm text-ink-500">…</span>
                <button x-show="n !== '…'" type="button" @click="page = n" :aria-current="n === pg.page ? 'page' : null" :aria-label="'Halaman ' + n"
                        class="grid h-9 min-w-[2.25rem] place-items-center rounded-lg border px-2 text-sm font-bold transition"
                        :class="n === pg.page ? 'border-brand-500 bg-brand-600 text-white' : 'border-ink-200 bg-white/80 text-ink-700 hover:bg-brand-50'" x-text="n"></button>
            </span>
        </template>
        <button type="button" :disabled="pg.page >= pg.pages" @click="page = pg.page + 1" class="grid h-9 min-w-[2.25rem] place-items-center rounded-lg border border-ink-200 bg-white/80 px-2 text-sm font-bold text-ink-700 transition hover:bg-brand-50 disabled:cursor-not-allowed disabled:opacity-40" aria-label="Halaman berikutnya"><x-lucide-chevron-right class="h-4 w-4" aria-hidden="true" /></button>
    </nav>
</div>
