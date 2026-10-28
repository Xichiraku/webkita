<x-layouts.admin title="Pengguna" section="pengguna">
<div x-data="{ q: '', role: 'semua', page: 1,
        roleOf(r) { return ['admin', 'developer'].includes(r) ? r : 'user'; },
        cnt(r) { return r === 'semua' ? users.length : users.filter((u) => this.roleOf(u.role) === r).length; },
        get rows() { return users.filter((u) => (this.role === 'semua' || this.roleOf(u.role) === this.role) && matches(this.q, userName(u), u.email, u.username)); },
        get pg() { return paginate(this.rows, this.page); },
        ROLE: { admin: ['Admin', 'bg-brand-600 text-white'], developer: ['Developer', 'bg-[#E0E7FF] text-[#3730A3]'], user: ['User', 'bg-ink-100 text-ink-700'] } }">
    <div>
        <p class="text-sm font-semibold text-brand-700">Admin</p>
        <h1 class="mt-1 text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">Manajemen Pengguna</h1>
        <p class="mt-1 text-ink-600">Daftar akun terdaftar di Webkita.</p>
    </div>

    <section aria-label="Daftar pengguna" class="mt-6">
        <div class="card overflow-hidden rounded-3xl">
            <div class="space-y-4 p-5 sm:p-6">
                <div class="relative">
                    <label for="search" class="sr-only">Cari pengguna</label>
                    <x-lucide-search class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-ink-500" aria-hidden="true" />
                    <input id="search" type="search" x-model.debounce.200ms="q" @input="page = 1" placeholder="Cari nama atau email…" autocomplete="off" class="{{ Ui::A_INPUT }} pl-10 pr-3">
                </div>
                <div class="no-scrollbar -mx-1 overflow-x-auto px-1">
                    <div class="flex w-max gap-2" role="group" aria-label="Filter role">
                        <template x-for="[k, label] in [['semua', 'Semua'], ['admin', 'Admin'], ['developer', 'Developer'], ['user', 'User']]" :key="k">
                            <button type="button" :aria-pressed="k === role" @click="role = k; page = 1"
                                    class="inline-flex items-center gap-2 whitespace-nowrap rounded-lg border px-3.5 py-2 text-sm font-semibold transition"
                                    :class="k === role ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-ink-200 bg-white/80 text-ink-700 hover:border-brand-300 hover:bg-brand-50'">
                                <span x-text="label"></span><span class="text-xs font-bold" x-text="cnt(k)"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <p x-show="loading" class="px-6 pb-10 text-center text-sm font-semibold text-ink-600" role="status">Memuat data…</p>
            <p x-show="!loading && !rows.length" x-cloak class="px-6 pb-12 text-center text-ink-600">Tidak ada pengguna yang cocok.</p>

            <div x-show="!loading && rows.length" x-cloak>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-ink-100 text-left text-xs font-bold uppercase tracking-wider text-ink-500">
                                <th scope="col" class="px-5 py-3 sm:pl-6">Pengguna</th><th scope="col" class="px-3 py-3">Kontak</th><th scope="col" class="px-3 py-3">Role</th>
                                <th scope="col" class="px-3 py-3">Status</th><th scope="col" class="px-3 py-3">Bergabung</th><th scope="col" class="px-3 py-3 pr-5 text-right sm:pr-6"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(u, i) in pg.items" :key="u.id">
                                <tr class="row-in border-b border-ink-100 last:border-0 hover:bg-brand-50/40" :style="`--d: ${i * 35}ms`">
                                    <td class="px-5 py-3.5 sm:pl-6">
                                        <div class="flex items-center gap-3">
                                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-extrabold text-brand-800" x-text="initials(userName(u))"></span>
                                            <div class="min-w-0"><p class="truncate font-bold text-ink-900" x-text="userName(u)"></p><p class="truncate text-xs text-ink-600" x-text="'@' + u.username"></p></div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3.5"><p class="text-ink-800" x-text="u.email"></p><p class="text-xs text-ink-600" x-text="u.phone || '–'"></p></td>
                                    <td class="px-3 py-3.5"><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" :class="ROLE[roleOf(u.role)][1]" x-text="ROLE[roleOf(u.role)][0]"></span></td>
                                    <td class="px-3 py-3.5"><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold" :class="u.is_aktif === 'Y' ? 'bg-[#DCFCE7] text-[#15803D]' : 'bg-ink-100 text-ink-700'" x-text="u.is_aktif === 'Y' ? 'Aktif' : 'Belum aktif'"></span></td>
                                    <td class="whitespace-nowrap px-3 py-3.5 text-ink-700" x-text="fmtDate(u.created_at)"></td>
                                    <td class="px-3 py-3.5 pr-5 text-right sm:pr-6">
                                        @if (auth()->user()->isAdmin())
                                            <button type="button" x-show="u.id !== meId" @click="deleteUser(u)" class="inline-grid h-9 w-9 place-items-center rounded-lg border border-ink-200 bg-white/80 text-ink-700 transition hover:border-[#FECACA] hover:bg-[#FEE2E2] hover:text-[#B91C1C]" :aria-label="'Hapus ' + u.username"><x-lucide-trash-2 class="h-4 w-4" aria-hidden="true" /></button>
                                        @endif
                                    </td>
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
