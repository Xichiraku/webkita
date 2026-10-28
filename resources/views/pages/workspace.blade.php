@php
    $isClient = $role === 'client';
    $backUrl = $isClient ? route('orderan') : route('developer.projects');
    $tabs = [
        ['ringkasan', 'Ringkasan', 'layout-dashboard'],
        ['milestone', 'Milestone', 'flag'],
        ['diskusi', 'Diskusi', 'messages-square'],
        ['chat', 'Chat', 'message-circle'],
    ];
    $price = \App\Support\Price::parse($project->plan_price_range);
@endphp
<x-layouts.site :title="$project->project_title">
<main class="relative overflow-hidden bg-ink-50"
      x-data="{ tab: ['ringkasan', 'milestone', 'diskusi', 'chat'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'ringkasan',
                progress: {{ $project->progress_percent }}, dot: false, chatUnread: {{ $chatUnread }}, status: '{{ $project->status->value }}',
                statuses: @js(collect(\App\Enums\ProjectStatus::cases())->mapWithKeys(fn ($c) => [$c->value => ['label' => $c->label(), 'chip' => $c->chip()]])),
                go(t) { this.tab = t; history.replaceState(null, '', '#' + t); } }"
      @workspace-progress.window="progress = $event.detail.progress_percent; if ($event.detail.status) status = $event.detail.status"
      @workspace-attention.window="dot = $event.detail"
      @workspace-chat-unread.window="chatUnread = $event.detail">
    <div class="pointer-events-none absolute -left-24 top-0 h-72 w-72 animate-blob rounded-full bg-brand-100 opacity-70 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-5xl px-5 pb-20 pt-8 sm:px-8 sm:pt-10">
        <a href="{{ $backUrl }}" class="group inline-flex items-center gap-2 rounded-md text-sm font-semibold text-ink-700 transition hover:text-brand-700">
            <x-lucide-arrow-left class="h-4 w-4 transition-transform group-hover:-translate-x-1" aria-hidden="true" /> {{ $isClient ? 'Kembali ke Orderan' : 'Kembali ke Proyek Saya' }}
        </a>

        {{-- Kepala workspace --}}
        <header class="mt-5 animate-rise rounded-3xl border border-ink-200 bg-white p-6 shadow-soft sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-bold tracking-wide text-ink-600">PROJECT WORKSPACE · #{{ $project->id }}</p>
                    <h1 class="mt-1 break-words text-2xl font-extrabold tracking-tight text-ink-900 sm:text-3xl">{{ $project->project_title }}</h1>
                    <p class="mt-1 text-sm text-ink-600">
                        {{ $project->plan_title ? "Paket {$project->plan_title} · " : '' }}{{ Ui::category($project->category) }}
                    </p>
                </div>
                <span class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-3 py-1 text-xs font-bold {{ $project->status->chip() }}" :class="statuses[status].chip" x-text="statuses[status].label">{{ $project->status->label() }}</span>
            </div>

            <div class="mt-5">
                <div class="mb-1.5 flex items-center justify-between text-sm"><span class="font-semibold text-ink-600">Progres keseluruhan</span><span class="font-extrabold text-ink-900" x-text="progress + '%'">{{ $project->progress_percent }}%</span></div>
                <div class="h-2.5 overflow-hidden rounded-full bg-ink-100" role="progressbar" :aria-valuenow="progress" aria-valuenow="{{ $project->progress_percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progres keseluruhan proyek">
                    <div class="h-full rounded-full bg-gradient-to-r from-brand-600 to-brand-400 transition-[width] duration-500" :style="'width:' + progress + '%'" style="width: {{ $project->progress_percent }}%"></div>
                </div>
            </div>
        </header>

        {{-- Tab --}}
        <div class="no-scrollbar mt-6 overflow-x-auto">
            <div class="flex w-max gap-2" role="tablist" aria-label="Bagian workspace">
                @foreach ($tabs as [$key, $label, $icon])
                    <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}" :aria-selected="tab === '{{ $key }}'" @click="go('{{ $key }}')"
                            class="inline-flex items-center gap-2 whitespace-nowrap rounded-xl border px-4 py-2.5 text-sm font-bold transition {{ Ui::FOCUS }}"
                            :class="tab === '{{ $key }}' ? 'border-brand-600 bg-brand-600 text-white shadow-soft' : 'border-ink-200 bg-white text-ink-700 hover:border-brand-300 hover:bg-brand-50'">
                        <x-dynamic-component :component="'lucide-'.$icon" class="h-4 w-4" aria-hidden="true" /> {{ $label }}
                        @if ($key === 'chat')
                            <span x-show="chatUnread > 0 && tab !== 'chat'" x-cloak class="grid min-w-[1.25rem] place-items-center rounded-full bg-[#EF4444] px-1.5 text-[11px] font-extrabold leading-5 text-white" x-text="chatUnread" role="img" :aria-label="chatUnread + ' pesan belum dibaca'"></span>
                        @endif
                        @if ($key === 'milestone')
                            <span x-show="dot" x-cloak class="h-2.5 w-2.5 rounded-full bg-[#F59E0B]" role="img" aria-label="{{ $isClient ? 'Ada hasil menunggu review' : 'Ada revisi yang perlu dikerjakan' }}"></span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Ringkasan --}}
        <section id="panel-ringkasan" role="tabpanel" aria-labelledby="tab-ringkasan" x-show="tab === 'ringkasan'" class="mt-5">
            <div class="grid gap-5 lg:grid-cols-3">
                <div class="space-y-5 rounded-3xl border border-ink-200 bg-white p-6 shadow-soft sm:p-8 lg:col-span-2">
                    <h2 class="text-lg font-extrabold text-ink-900">Detail Proyek</h2>
                    <div><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Deskripsi</p><p class="mt-1 whitespace-pre-line break-words leading-relaxed text-ink-900">{{ $project->description }}</p></div>
                    <div><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Skill dibutuhkan</p><p class="mt-1 text-ink-900">{{ $project->skills }}</p></div>
                    @if ($project->additional_notes)
                        <div><p class="text-xs font-bold uppercase tracking-wide text-ink-600">Catatan tambahan</p><p class="mt-1 whitespace-pre-line break-words text-ink-900">{{ $project->additional_notes }}</p></div>
                    @endif
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-ink-600">Lampiran pengajuan</p>
                        @forelse ($project->attachments->whereNull('update_id')->whereNull('message_id') as $att)
                            <a href="{{ url('/api/attachments/download/'.$att->id) }}" class="mt-1.5 flex items-center gap-2 break-all font-semibold text-brand-700 hover:underline"><x-lucide-paperclip class="h-4 w-4 shrink-0" aria-hidden="true" />{{ $att->file_name }} <span class="font-normal text-ink-500">({{ Ui::fileSize($att->file_size) }})</span></a>
                        @empty
                            <p class="mt-1 text-ink-500">Tidak ada lampiran.</p>
                        @endforelse
                    </div>
                </div>

                <aside class="space-y-5">
                    <div class="rounded-3xl border border-ink-200 bg-white p-6 shadow-soft">
                        <h2 class="text-lg font-extrabold text-ink-900">{{ $isClient ? 'Developer' : 'Klien' }}</h2>
                        @if ($isClient)
                            <p class="mt-3 flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-full bg-brand-100 text-sm font-extrabold text-brand-800">{{ Ui::initials($project->developer?->name) }}</span><span class="font-bold text-ink-900">{{ $project->developer?->name ?? 'Belum ditugaskan' }}</span></p>
                        @else
                            <p class="mt-3 font-bold text-ink-900">{{ $project->contact_name }}</p>
                            <a href="{{ Ui::waLink($project->contact_phone) }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:underline"><x-lucide-message-circle class="h-4 w-4" aria-hidden="true" />{{ $project->contact_phone }}</a>
                        @endif
                    </div>
                    <div class="rounded-3xl border border-ink-200 bg-white p-6 shadow-soft">
                        <h2 class="text-lg font-extrabold text-ink-900">Ringkasan</h2>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-ink-600">Dibuat</dt><dd class="font-semibold text-ink-900">{{ Ui::date($project->created_at) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-600">Harga final</dt><dd class="font-semibold text-ink-900">{{ $price ? Ui::rp($price) : '–' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-600">Kuota revisi</dt><dd class="font-semibold text-ink-900">{{ $project->revision_limit === null ? 'Tanpa batas' : "{$project->revisions_used} dari {$project->revision_limit} terpakai" }}</dd></div>
                        </dl>
                    </div>
                </aside>
            </div>

            {{-- Riwayat aktivitas (Fitur 6) --}}
            <div class="mt-5 rounded-3xl border border-ink-200 bg-white p-6 shadow-soft sm:p-8"
                 x-data="activityTimeline({{ $project->id }}, @js($activities), @js($activitiesHasMore))">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="flex items-center gap-2 text-lg font-extrabold text-ink-900"><x-lucide-history class="h-5 w-5 text-brand-600" aria-hidden="true" /> Riwayat Aktivitas</h2>
                    <span class="text-sm text-ink-600" x-text="items.length + ' aktivitas'"></span>
                </div>

                {{-- ikon untuk baris yang ditambahkan lewat JavaScript --}}
                <div hidden x-ref="icons">
                    @foreach (\App\Support\ActivityPresenter::icons() as $icon)
                        <span data-icon="{{ $icon }}"><x-dynamic-component :component="'lucide-'.$icon" class="h-4 w-4" aria-hidden="true" /></span>
                    @endforeach
                </div>

                <p class="sr-only" role="status" aria-live="polite" x-text="announce"></p>

                <div class="relative mt-4">
                    <div x-ref="box" @scroll.passive="onScroll()" class="scroll-thin max-h-[26rem] overflow-y-auto pr-2">
                        <button type="button" x-show="hasMore" x-cloak @click="loadOlder()" :disabled="loading"
                                class="mb-3 w-full rounded-xl border-2 border-ink-200 bg-white px-4 py-2 text-sm font-bold text-ink-700 transition hover:border-brand-400 hover:bg-brand-50 disabled:opacity-60">
                            <span x-text="loading ? 'Memuat…' : 'Muat sebelumnya'"></span>
                        </button>
                        <p x-show="error" x-cloak class="mb-3 flex items-center justify-between gap-3 rounded-xl bg-[#FEF2F2] p-3 text-sm font-semibold text-[#7F1D1D]" role="alert">
                            <span x-text="error"></span>
                            <button type="button" @click="loadOlder()" class="rounded-lg border border-[#FECACA] bg-white px-3 py-1 text-xs font-bold text-[#B91C1C] hover:bg-[#FEE2E2]">Coba lagi</button>
                        </p>

                        <p x-show="!items.length" class="py-8 text-center text-ink-500">Belum ada aktivitas.</p>

                        <ol class="space-y-4">
                            <template x-for="a in items" :key="a.id">
                                <li class="row-in flex gap-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-100 text-brand-700" x-html="icon(a.icon)" aria-hidden="true"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="break-words text-sm font-semibold leading-snug text-ink-900" x-text="a.text"></p>
                                        <div x-show="a.type === 'status.changed'" class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="chip(a.meta.from)[1]" x-text="chip(a.meta.from)[0]"></span>
                                            <span class="text-ink-400" aria-hidden="true">→</span>
                                            <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="chip(a.meta.to)[1]" x-text="chip(a.meta.to)[0]"></span>
                                        </div>
                                        <p class="mt-0.5 text-xs text-ink-500">
                                            <span x-text="a.actor.name"></span> · <time :datetime="a.created_at" :title="absolute(a.created_at)" x-text="relative(a.created_at)"></time>
                                        </p>
                                    </div>
                                </li>
                            </template>
                        </ol>
                    </div>

                    <button type="button" x-show="newCount > 0" x-cloak @click="scrollBottom()"
                            class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow-lift transition hover:bg-brand-700">
                        ↓ Aktivitas baru (<span x-text="newCount"></span>)
                    </button>
                </div>
            </div>
        </section>

        {{-- Milestone (Fitur 1 & 2) --}}
        <section id="panel-milestone" role="tabpanel" aria-labelledby="tab-milestone" x-show="tab === 'milestone'" x-cloak class="mt-5"
                 x-data="milestoneBoard({{ $project->id }}, @js($milestones), @js(! $isClient), @js(['status' => $project->status->value, 'revisions_used' => $project->revisions_used, 'revision_limit' => $project->revision_limit]))">
            <div class="rounded-3xl border border-ink-200 bg-white p-6 shadow-soft sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="flex items-center gap-2 text-lg font-extrabold text-ink-900"><x-lucide-flag class="h-5 w-5 text-brand-600" aria-hidden="true" /> Milestone</h2>
                        <p class="mt-0.5 text-sm text-ink-600" x-text="summary"></p>
                    </div>
                    <button type="button" x-show="canManage && !projectDone && milestones.length < 10" x-cloak @click="adding = !adding"
                            class="{{ Ui::BTN_PRIMARY }} px-4 py-2 text-sm"><x-lucide-plus class="h-4 w-4" aria-hidden="true" /> Tambah milestone</button>
                </div>

                <p x-show="error" x-cloak class="mt-4 rounded-xl bg-[#FEF2F2] p-3 text-sm font-semibold text-[#7F1D1D]" role="alert" x-text="error"></p>

                <p x-show="projectDone" x-cloak class="mt-4 flex items-center gap-2 rounded-xl bg-[#DCFCE7] p-3 text-sm font-bold text-[#15803D]" role="status"><x-lucide-party-popper class="h-4 w-4" aria-hidden="true" /> Proyek selesai. Semua milestone telah disetujui.</p>

                {{-- Kuota revisi proyek (disepakati dengan klien) --}}
                <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl bg-ink-50 px-4 py-2.5 text-sm">
                    <span class="flex items-center gap-2 font-semibold text-ink-800"><x-lucide-rotate-ccw class="h-4 w-4 text-brand-600" aria-hidden="true" /><span x-text="quotaText"></span></span>
                    <button type="button" x-show="canManage && !editingLimit" @click="startLimit()" class="rounded-md text-xs font-bold text-brand-700 hover:underline {{ Ui::FOCUS }}">Ubah kuota</button>
                    <form x-show="editingLimit" x-cloak @submit.prevent="saveLimit()" class="flex flex-wrap items-center gap-2">
                        <label for="quota-input" class="sr-only">Kuota revisi (kosongkan untuk tanpa batas)</label>
                        <input id="quota-input" type="number" min="0" max="50" x-model="limitDraft" placeholder="Tanpa batas" class="w-28 rounded-lg border-2 border-ink-200 bg-white px-2 py-1 text-sm {{ Ui::FOCUS }}">
                        <button type="submit" :disabled="busy" class="{{ Ui::BTN_PRIMARY }} px-3 py-1 text-xs">Simpan</button>
                        <button type="button" @click="editingLimit = false" class="{{ Ui::BTN_SECONDARY }} px-3 py-1 text-xs">Batal</button>
                        <span x-show="limitError" class="w-full text-xs font-semibold text-[#B91C1C]" x-text="limitError"></span>
                    </form>
                </div>

                {{-- Tambah milestone --}}
                <form x-show="adding" x-cloak @submit.prevent="add()" class="mt-4 space-y-3 rounded-2xl border border-ink-200 bg-ink-50 p-4">
                    <div>
                        <label for="ms-title" class="text-sm font-bold text-ink-800">Judul</label>
                        <input id="ms-title" type="text" x-model="draft.title" maxlength="100" required class="mt-1 w-full rounded-xl border-2 border-ink-200 bg-white px-3 py-2 text-sm {{ Ui::FOCUS }}">
                    </div>
                    <div>
                        <label for="ms-desc" class="text-sm font-bold text-ink-800">Deskripsi <span class="font-normal text-ink-500">(opsional)</span></label>
                        <textarea id="ms-desc" x-model="draft.description" rows="2" maxlength="1000" class="mt-1 w-full rounded-xl border-2 border-ink-200 bg-white px-3 py-2 text-sm {{ Ui::FOCUS }}"></textarea>
                    </div>
                    <div>
                        <label for="ms-due" class="text-sm font-bold text-ink-800">Tenggat <span class="font-normal text-ink-500">(opsional)</span></label>
                        <input id="ms-due" type="date" x-model="draft.due_date" :min="today" class="mt-1 rounded-xl border-2 border-ink-200 bg-white px-3 py-2 text-sm {{ Ui::FOCUS }}">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" :disabled="busy" class="{{ Ui::BTN_PRIMARY }} px-4 py-2 text-sm">Simpan</button>
                        <button type="button" @click="adding = false" class="{{ Ui::BTN_SECONDARY }} px-4 py-2 text-sm">Batal</button>
                    </div>
                </form>

                <div x-show="!milestones.length" class="mt-6 rounded-2xl border border-dashed border-ink-300 px-6 py-10 text-center text-ink-600">
                    <x-lucide-flag class="mx-auto h-8 w-8 text-ink-400" aria-hidden="true" />
                    <p class="mt-3 font-bold text-ink-800">Belum ada milestone</p>
                    <p class="mt-1 text-sm" x-text="canManage ? 'Tambahkan milestone untuk memecah pekerjaan menjadi tahapan.' : 'Developer belum menyusun milestone.'"></p>
                </div>

                <ol class="mt-5 space-y-3">
                    <template x-for="(m, idx) in milestones" :key="m.id">
                        <li class="rounded-2xl border border-ink-200 bg-white">
                            <div class="flex flex-wrap items-center gap-3 p-4">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-extrabold text-brand-800" x-text="idx + 1"></span>
                                <button type="button" class="min-w-[9rem] flex-1 rounded-md text-left {{ Ui::FOCUS }}" @click="toggle(m)" :aria-expanded="open === m.id">
                                    <span class="block break-words font-bold text-ink-900" x-text="m.title"></span>
                                    <span class="mt-0.5 block text-xs text-ink-600">
                                        <span x-show="m.due_date" x-text="'Tenggat ' + fmtDate(m.due_date) + ' · '"></span><span x-text="m.updates_count + ' update'"></span>
                                    </span>
                                </button>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="chip(m.status)[1]" x-text="chip(m.status)[0]"></span>
                                <span class="w-12 text-right text-sm font-extrabold text-ink-900" x-text="m.percent + '%'"></span>
                                <div x-show="canManage && !projectDone" class="flex items-center gap-1">
                                    <button type="button" x-show="canMove(m, -1)" @click="move(m, -1)" :disabled="busy" class="rounded-lg p-1.5 text-ink-600 hover:bg-ink-100 {{ Ui::FOCUS }}" :aria-label="'Naikkan ' + m.title"><x-lucide-arrow-up class="h-4 w-4" aria-hidden="true" /></button>
                                    <button type="button" x-show="canMove(m, 1)" @click="move(m, 1)" :disabled="busy" class="rounded-lg p-1.5 text-ink-600 hover:bg-ink-100 {{ Ui::FOCUS }}" :aria-label="'Turunkan ' + m.title"><x-lucide-arrow-down class="h-4 w-4" aria-hidden="true" /></button>
                                    <button type="button" @click="startEdit(m)" class="rounded-lg p-1.5 text-ink-600 hover:bg-ink-100 {{ Ui::FOCUS }}" :aria-label="'Ubah ' + m.title"><x-lucide-pencil class="h-4 w-4" aria-hidden="true" /></button>
                                    <button type="button" x-show="m.status === 'planned' && !m.updates_count" @click="remove(m)" :disabled="busy" class="rounded-lg p-1.5 text-[#B91C1C] hover:bg-[#FEE2E2] {{ Ui::FOCUS }}" :aria-label="'Hapus ' + m.title"><x-lucide-trash-2 class="h-4 w-4" aria-hidden="true" /></button>
                                </div>
                            </div>
                            <div class="px-4 pb-3"><div class="h-1.5 overflow-hidden rounded-full bg-ink-100"><div class="h-full rounded-full bg-brand-500 transition-[width] duration-500" :style="'width:' + m.percent + '%'"></div></div></div>

                            {{-- Ubah milestone --}}
                            <form x-show="editing && editing.id === m.id" x-cloak @submit.prevent="saveEdit()" class="space-y-3 border-t border-ink-200 bg-ink-50 p-4">
                                <template x-if="editing && editing.id === m.id">
                                    <div class="space-y-3">
                                        <p x-show="editing.locked" class="text-xs text-ink-600">Judul dan deskripsi terkunci karena milestone sudah dikerjakan. Hanya tenggat yang dapat diubah.</p>
                                        <input type="text" x-model="editing.title" :disabled="editing.locked" maxlength="100" aria-label="Judul milestone" class="w-full rounded-xl border-2 border-ink-200 bg-white px-3 py-2 text-sm disabled:bg-ink-100 {{ Ui::FOCUS }}">
                                        <textarea x-model="editing.description" :disabled="editing.locked" rows="2" maxlength="1000" aria-label="Deskripsi milestone" class="w-full rounded-xl border-2 border-ink-200 bg-white px-3 py-2 text-sm disabled:bg-ink-100 {{ Ui::FOCUS }}"></textarea>
                                        <input type="date" x-model="editing.due_date" aria-label="Tenggat" class="rounded-xl border-2 border-ink-200 bg-white px-3 py-2 text-sm {{ Ui::FOCUS }}">
                                        <div class="flex gap-2">
                                            <button type="submit" :disabled="busy" class="{{ Ui::BTN_PRIMARY }} px-4 py-2 text-sm">Simpan</button>
                                            <button type="button" @click="editing = null" class="{{ Ui::BTN_SECONDARY }} px-4 py-2 text-sm">Batal</button>
                                        </div>
                                    </div>
                                </template>
                            </form>

                            {{-- Detail: deskripsi, update, form --}}
                            <div x-show="open === m.id" x-cloak class="space-y-4 border-t border-ink-200 p-4">
                                <p x-show="m.description" class="whitespace-pre-line break-words text-sm text-ink-800" x-text="m.description"></p>

                                {{-- Tinjau hasil (klien) --}}
                                <template x-if="!canManage && m.status === 'submitted' && pending(m)">
                                    <div class="rounded-2xl border-2 border-[#FDE68A] bg-[#FFFBEB] p-4">
                                        <h3 class="flex items-center gap-2 text-sm font-extrabold text-ink-900"><x-lucide-clipboard-check class="h-4 w-4 text-[#B45309]" aria-hidden="true" /> Tinjau Hasil</h3>
                                        <p class="mt-2 font-bold text-ink-900" x-text="pending(m).title"></p>
                                        <p class="mt-1 whitespace-pre-line break-words text-sm text-ink-800" x-text="pending(m).note"></p>
                                        <ul class="mt-2 space-y-1">
                                            <template x-for="l in (pending(m).links ?? [])" :key="l"><li><a :href="l" target="_blank" rel="noopener noreferrer" class="break-all text-sm font-semibold text-brand-700 hover:underline" x-text="l"></a></li></template>
                                            <template x-for="f in pending(m).files" :key="f.id"><li><a :href="'/api/attachments/download/' + f.id" class="break-all text-sm font-semibold text-brand-700 hover:underline" x-text="f.file_name"></a></li></template>
                                        </ul>
                                        <div class="mt-4 flex flex-wrap items-center gap-2">
                                            <button type="button" @click="approve(m)" :disabled="busy" class="{{ Ui::BTN_PRIMARY }} px-4 py-2 text-sm"><x-lucide-check class="h-4 w-4" aria-hidden="true" /> Setujui Hasil</button>
                                            <button type="button" @click="openRevision(m)" :disabled="busy || !quotaLeft" class="{{ Ui::BTN_SECONDARY }} px-4 py-2 text-sm"><x-lucide-rotate-ccw class="h-4 w-4" aria-hidden="true" /> Minta Revisi</button>
                                        </div>
                                        <p x-show="!quotaLeft" class="mt-2 text-xs font-semibold text-[#B45309]" x-text="'Kuota revisi proyek (' + project.revision_limit + ') sudah habis. Setujui hasil atau sepakati tambahan kuota dengan developer.'"></p>
                                    </div>
                                </template>
                                <p x-show="canManage && m.status === 'submitted'" class="flex items-center gap-2 rounded-xl bg-[#FEF3C7] p-3 text-sm font-semibold text-[#B45309]"><x-lucide-hourglass class="h-4 w-4" aria-hidden="true" /> Menunggu review klien.</p>
                                <p x-show="m.status === 'revision'" class="flex items-center gap-2 rounded-xl bg-[#FEE2E2] p-3 text-sm font-semibold text-[#B91C1C]"><x-lucide-rotate-ccw class="h-4 w-4" aria-hidden="true" /> <span x-text="canManage ? 'Revisi diminta. Perbaiki lalu serahkan hasil baru.' : 'Revisi sedang dikerjakan developer.'"></span></p>

                                {{-- Form update (developer) --}}
                                <template x-if="canManage && !projectDone">
                                    <div>
                                        <p x-show="!canReceive(m)" class="flex items-center gap-2 rounded-xl bg-ink-100 p-3 text-sm font-semibold text-ink-700"><x-lucide-lock class="h-4 w-4" aria-hidden="true" /><span x-text="disabledReason(m)"></span></p>
                                        <form x-show="canReceive(m)" @submit.prevent="submit(m)" class="space-y-3 rounded-2xl border border-ink-200 bg-ink-50 p-4">
                                            <div class="flex gap-2" role="radiogroup" aria-label="Jenis update">
                                                <label class="cursor-pointer rounded-xl border-2 px-3 py-1.5 text-sm font-bold" :class="form.type === 'progress' ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-ink-200 bg-white text-ink-700'"><input type="radio" value="progress" x-model="form.type" class="sr-only"> Progres</label>
                                                <label class="cursor-pointer rounded-xl border-2 px-3 py-1.5 text-sm font-bold" :class="form.type === 'result' ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-ink-200 bg-white text-ink-700'"><input type="radio" value="result" x-model="form.type" class="sr-only"> Serahkan hasil</label>
                                            </div>
                                            <div x-show="form.type === 'progress'">
                                                <label class="flex justify-between text-sm font-bold text-ink-800">Persentase <span x-text="form.percent + '%'"></span></label>
                                                <input type="range" min="0" max="99" step="1" x-model.number="form.percent" :aria-label="'Persentase milestone ' + m.title" class="mt-1 w-full accent-brand-600">
                                                <p class="text-xs text-ink-600" x-text="'Tidak boleh lebih kecil dari ' + m.percent + '%.'"></p>
                                                <p class="text-xs font-semibold text-[#B91C1C]" x-text="errFor('percent')"></p>
                                            </div>
                                            <div x-show="form.type === 'result'">
                                                <label class="text-sm font-bold text-ink-800">Judul hasil</label>
                                                <input type="text" x-model="form.title" maxlength="100" class="mt-1 w-full rounded-xl border-2 border-ink-200 bg-white px-3 py-2 text-sm {{ Ui::FOCUS }}">
                                                <p class="text-xs font-semibold text-[#B91C1C]" x-text="errFor('title')"></p>
                                            </div>
                                            <div>
                                                <label class="text-sm font-bold text-ink-800">Catatan</label>
                                                <textarea x-model="form.note" rows="3" maxlength="2000" class="mt-1 w-full rounded-xl border-2 border-ink-200 bg-white px-3 py-2 text-sm {{ Ui::FOCUS }}"></textarea>
                                                <p class="text-xs font-semibold text-[#B91C1C]" x-text="errFor('note')"></p>
                                            </div>
                                            <div>
                                                <label class="text-sm font-bold text-ink-800">Tautan <span class="font-normal text-ink-500">(satu per baris, https, maks. 5)</span></label>
                                                <textarea x-model="form.links" rows="2" class="mt-1 w-full rounded-xl border-2 border-ink-200 bg-white px-3 py-2 text-sm {{ Ui::FOCUS }}"></textarea>
                                                <p class="text-xs font-semibold text-[#B91C1C]" x-text="errFor('links')"></p>
                                            </div>
                                            <div>
                                                <label class="text-sm font-bold text-ink-800">Berkas <span class="font-normal text-ink-500">(PDF, PNG, JPG, ZIP · maks. 10 berkas, 25 MB per berkas)</span></label>
                                                <input type="file" multiple x-ref="files" @change="pickFiles($event)" accept=".pdf,.png,.jpg,.jpeg,.zip" class="mt-1 block w-full text-sm">
                                                <p class="text-xs font-semibold text-[#B91C1C]" x-text="errFor('files')"></p>
                                            </div>
                                            <p x-show="formError && !Object.keys(formErrors).length" class="text-sm font-semibold text-[#B91C1C]" role="alert" x-text="formError"></p>
                                            <button type="submit" :disabled="busy" class="{{ Ui::BTN_PRIMARY }} px-4 py-2 text-sm"><span x-text="busy ? 'Mengirim…' : (form.type === 'result' ? 'Serahkan hasil' : 'Kirim progres')"></span></button>
                                        </form>
                                    </div>
                                </template>

                                {{-- Daftar update --}}
                                <div>
                                    <h3 class="text-sm font-extrabold text-ink-900">Update</h3>
                                    <p x-show="updates[m.id]?.loading" class="mt-2 text-sm text-ink-500">Memuat…</p>
                                    <p x-show="updates[m.id] && !updates[m.id].loading && !updates[m.id].items.length" class="mt-2 text-sm text-ink-500">Belum ada update.</p>
                                    <ul class="mt-2 space-y-3">
                                        <template x-for="u in (updates[m.id]?.items ?? [])" :key="u.id">
                                            <li class="rounded-xl border border-ink-200 p-3">
                                                <div class="flex flex-wrap items-center gap-2 text-xs text-ink-600">
                                                    <span class="rounded-full px-2 py-0.5 font-bold" :class="u.type === 'result' ? 'bg-brand-100 text-brand-800' : 'bg-ink-100 text-ink-700'" x-text="u.type === 'result' ? 'Hasil' : 'Progres ' + u.percent + '%'"></span>
                                                    <span x-show="u.type === 'result' && u.review_status" class="rounded-full bg-ink-100 px-2 py-0.5 font-bold text-ink-700" x-text="reviewLabel(u)"></span>
                                                    <span x-text="u.author.name + ' · ' + fmtTime(u.created_at)"></span>
                                                </div>
                                                <p x-show="u.title" class="mt-1 font-bold text-ink-900" x-text="u.title"></p>
                                                <p class="mt-1 whitespace-pre-line break-words text-sm text-ink-800" x-text="u.note"></p>
                                                <ul class="mt-2 space-y-1">
                                                    <template x-for="l in (u.links ?? [])" :key="l"><li><a :href="l" target="_blank" rel="noopener noreferrer" class="break-all text-sm font-semibold text-brand-700 hover:underline" x-text="l"></a></li></template>
                                                    <template x-for="f in u.files" :key="f.id"><li><a :href="'/api/attachments/download/' + f.id" class="break-all text-sm font-semibold text-brand-700 hover:underline"><span x-text="f.file_name"></span> <span class="font-normal text-ink-500" x-text="'(' + fmtSize(f.file_size) + ')'"></span></a></li></template>
                                                </ul>
                                            </li>
                                        </template>
                                    </ul>
                                    <button type="button" x-show="updates[m.id]?.hasMore" @click="loadUpdates(m.id, true)" class="{{ Ui::BTN_SECONDARY }} mt-3 px-4 py-2 text-sm">Muat lebih lama</button>
                                </div>
                            </div>
                        </li>
                    </template>
                </ol>
            </div>

            {{-- Minta revisi --}}
            <x-modal show="revising !== null" close="revising = null" labelledby="rev-title">
                <form @submit.prevent="sendRevision()" class="p-6 sm:p-8">
                    <h2 id="rev-title" class="text-xl font-extrabold text-ink-900">Minta Revisi</h2>
                    <p class="mt-1 text-sm text-ink-600" x-text="revising ? 'Milestone: ' + revising.title : ''"></p>
                    <p class="mt-3 rounded-xl bg-ink-50 px-3 py-2 text-sm font-semibold text-ink-800" x-text="quotaText"></p>
                    <label for="rev-reason" class="mt-4 block text-sm font-bold text-ink-800">Bagian yang perlu direvisi</label>
                    <textarea id="rev-reason" x-model="reason" rows="5" maxlength="2000" class="mt-1 w-full rounded-xl border-2 border-ink-200 bg-white px-3 py-2 text-sm {{ Ui::FOCUS }}"></textarea>
                    <p class="mt-1 flex justify-between text-xs" :class="reason.trim().length < 20 ? 'text-ink-500' : 'text-[#15803D]'"><span x-text="reason.trim().length + ' / minimal 20 karakter'"></span><span x-text="2000 - reason.length + ' tersisa'"></span></p>
                    <p x-show="reasonError" class="mt-2 text-sm font-semibold text-[#B91C1C]" role="alert" x-text="reasonError"></p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="revising = null" class="{{ Ui::BTN_SECONDARY }} px-4 py-2 text-sm">Batal</button>
                        <button type="submit" :disabled="busy || reason.trim().length < 20" class="{{ Ui::BTN_PRIMARY }} px-4 py-2 text-sm">Kirim</button>
                    </div>
                </form>
            </x-modal>
        </section>

        {{-- Diskusi: komentar (Fitur 3) --}}
        <section id="panel-diskusi" role="tabpanel" aria-labelledby="tab-diskusi" x-show="tab === 'diskusi'" x-cloak class="mt-5"
                 x-data="discussion({{ $project->id }}, @js($comments), @js($commentsHasMore), {{ auth()->id() }}, @js($resultRefs), @js(collect($milestones)->map(fn ($m) => ['id' => $m['id'], 'title' => $m['title']])->values()))">
            <div class="rounded-3xl border border-ink-200 bg-white p-6 shadow-soft sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="flex items-center gap-2 text-lg font-extrabold text-ink-900"><x-lucide-messages-square class="h-5 w-5 text-brand-600" aria-hidden="true" /> Diskusi</h2>
                    <div>
                        <label for="disc-filter" class="sr-only">Filter komentar</label>
                        <select id="disc-filter" x-model="filter" class="rounded-xl border-2 border-ink-200 bg-white px-3 py-1.5 text-sm font-semibold {{ Ui::FOCUS }}">
                            <option value="">Semua</option>
                            <template x-for="m in milestones" :key="m.id"><option :value="m.id" x-text="'Milestone: ' + m.title"></option></template>
                        </select>
                    </div>
                </div>

                <p x-show="error" x-cloak class="mt-4 rounded-xl bg-[#FEF2F2] p-3 text-sm font-semibold text-[#7F1D1D]" role="alert" x-text="error"></p>

                <div x-ref="box" class="scroll-thin mt-4 max-h-[28rem] overflow-y-auto pr-2">
                    <button type="button" x-show="hasMore" x-cloak @click="loadOlder()" :disabled="loading" class="{{ Ui::BTN_SECONDARY }} mb-3 w-full px-4 py-2 text-sm"><span x-text="loading ? 'Memuat…' : 'Muat sebelumnya'"></span></button>
                    <p x-show="!visible.length" class="py-8 text-center text-ink-500">Belum ada komentar.</p>
                    <ol class="space-y-3">
                        <template x-for="c in visible" :key="c.id">
                            <li class="row-in flex gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-100 text-xs font-extrabold text-brand-800" x-text="c.author.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase()" aria-hidden="true"></span>
                                <div class="min-w-0 flex-1 rounded-2xl px-4 py-2.5" >
                                    <p class="flex flex-wrap items-center gap-x-2 text-xs text-ink-600"><span class="font-bold text-ink-900" x-text="c.author.name"></span><span x-text="c.author.role === 'user' ? 'Klien' : 'Developer'"></span><time :datetime="c.created_at" x-text="time(c.created_at)"></time></p>
                                    <p x-show="c.update_id" class="mt-0.5 text-xs font-semibold text-brand-700" x-text="aboutLabel(c)"></p>
                                    <p x-show="!c.deleted" class="mt-1 whitespace-pre-line break-words text-sm text-ink-900" x-text="c.body"></p>
                                    <p x-show="c.deleted" class="mt-1 text-sm italic text-ink-500">Komentar ini telah dihapus</p>
                                    <button type="button" x-show="canDelete(c)" @click="remove(c)" class="mt-1 rounded-md text-xs font-bold text-[#B91C1C] hover:underline {{ Ui::FOCUS }}">Hapus</button>
                                </div>
                            </li>
                        </template>
                    </ol>
                </div>

                <form @submit.prevent="send()" class="mt-4 space-y-2 border-t border-ink-200 pt-4">
                    <label for="disc-body" class="sr-only">Tulis komentar</label>
                    <textarea id="disc-body" x-model="body" @keydown.ctrl.enter.prevent="send()" @keydown.meta.enter.prevent="send()" rows="3" placeholder="Tulis komentar…" class="w-full rounded-xl border-2 border-ink-200 bg-white px-3 py-2 text-sm {{ Ui::FOCUS }}"></textarea>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <label for="disc-about" class="text-xs font-bold text-ink-700">Tentang</label>
                            <select id="disc-about" x-model="about" class="max-w-[14rem] rounded-lg border-2 border-ink-200 bg-white px-2 py-1 text-xs {{ Ui::FOCUS }}">
                                <option value="">(umum)</option>
                                <template x-for="r in refs" :key="r.id"><option :value="r.id" x-text="(milestones.find((m) => m.id === r.milestone_id)?.title ?? 'Milestone') + ' · ' + (r.title ?? 'Hasil')"></option></template>
                            </select>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs" :class="left < 0 ? 'font-bold text-[#B91C1C]' : 'text-ink-500'" x-text="left + ' tersisa'"></span>
                            <button type="submit" :disabled="!canSend" class="{{ Ui::BTN_PRIMARY }} px-4 py-2 text-sm">Kirim</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        {{-- Chat klien dan developer (Fitur 5) --}}
        <section id="panel-chat" role="tabpanel" aria-labelledby="tab-chat" x-show="tab === 'chat'" x-cloak class="mt-5"
                 x-data="chatBoard({{ $project->id }}, @js($chatMessages), @js($chatHasMore), {{ $chatLastRead }}, {{ auth()->id() }}, @js($chatState), {{ $chatUnread }})"
                 x-effect="active = tab === 'chat'">
            <div class="rounded-3xl border border-ink-200 bg-white p-6 shadow-soft sm:p-8">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="flex items-center gap-2 text-lg font-extrabold text-ink-900"><x-lucide-message-circle class="h-5 w-5 text-brand-600" aria-hidden="true" /> Chat</h2>
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink-600" role="status">
                        <span class="h-2 w-2 rounded-full" :class="connected ? 'bg-[#22C55E]' : 'bg-[#F59E0B]'" aria-hidden="true"></span>
                        <span x-text="connected ? 'Terhubung' : 'Menyambungkan…'"></span>
                    </span>
                </div>

                <p x-show="state === 'no_dev'" class="mt-4 rounded-xl bg-ink-50 p-4 text-sm font-semibold text-ink-700">Chat tersedia setelah developer ditugaskan.</p>
                <p x-show="state === 'closed'" x-cloak class="mt-4 rounded-xl bg-ink-50 p-4 text-sm font-semibold text-ink-700">Chat sudah ditutup. Anda masih dapat membaca riwayat.</p>

                <div x-show="state !== 'no_dev'" class="relative mt-4">
                    <div x-ref="box" @scroll.passive="onScroll()" role="log" aria-live="polite" aria-label="Riwayat chat" class="scroll-thin h-[26rem] overflow-y-auto rounded-2xl bg-ink-50 p-4">
                        <button type="button" x-show="hasMore" x-cloak @click="loadOlder()" :disabled="loading" class="{{ Ui::BTN_SECONDARY }} mb-3 w-full px-4 py-2 text-sm"><span x-text="loading ? 'Memuat…' : 'Muat sebelumnya'"></span></button>
                        <p x-show="!items.length" class="py-16 text-center text-ink-500">Belum ada pesan. Mulai percakapan.</p>

                        <template x-for="(m, i) in items" :key="m.id">
                            <div>
                                <p x-show="showDay(i)" class="my-3 text-center text-xs font-bold text-ink-500" x-text="dayLabel(m.created_at)"></p>
                                <p x-show="m.id === dividerId" class="my-3 flex items-center gap-3 text-xs font-bold text-[#B45309]"><span class="h-px flex-1 bg-[#FDE68A]"></span>Pesan belum dibaca<span class="h-px flex-1 bg-[#FDE68A]"></span></p>
                                <div class="mb-2 flex" :class="m.author.id === {{ auth()->id() }} ? 'justify-end' : 'justify-start'">
                                    <div class="max-w-[85%] rounded-2xl px-4 py-2.5" :class="m.author.id === {{ auth()->id() }} ? 'bg-brand-600 text-white' : 'border border-ink-200 bg-white text-ink-900'">
                                        <p class="flex flex-wrap items-center gap-x-2 text-xs" :class="m.author.id === {{ auth()->id() }} ? 'text-white/80' : 'text-ink-600'">
                                            <span class="font-bold" x-text="m.author.name"></span>
                                            <span class="rounded-full px-1.5 py-px font-bold" :class="m.author.id === {{ auth()->id() }} ? 'bg-white/20' : 'bg-ink-100'" x-text="m.author.role === 'user' ? 'Klien' : 'Developer'"></span>
                                            <time :datetime="m.created_at" x-text="time(m.created_at)"></time>
                                        </p>
                                        <p x-show="m.deleted" class="mt-1 text-sm italic opacity-80">Pesan ini telah dihapus</p>
                                        <p x-show="!m.deleted && m.body" class="mt-1 whitespace-pre-line break-words text-sm" x-html="render(m.body)"></p>
                                        <div x-show="m.attachments.length" class="mt-2 flex flex-wrap gap-2">
                                            <template x-for="a in m.attachments" :key="a.id">
                                                <a :href="fileUrl(a)" :target="isImage(a) ? '_blank' : null" class="block overflow-hidden rounded-xl border border-ink-200 bg-white text-ink-900">
                                                    <img x-show="isImage(a)" :src="fileUrl(a)" :alt="a.file_name" loading="lazy" class="h-24 w-24 object-cover">
                                                    <span x-show="!isImage(a)" class="flex items-center gap-2 px-3 py-2 text-xs font-semibold"><x-lucide-file-text class="h-4 w-4 text-brand-600" aria-hidden="true" /><span class="max-w-[10rem] truncate" x-text="a.file_name"></span><span class="font-normal text-ink-500" x-text="fmtSize(a.file_size)"></span></span>
                                                </a>
                                            </template>
                                        </div>
                                        <button type="button" x-show="canDelete(m)" @click="remove(m)" class="mt-1 rounded-md text-xs font-bold underline {{ Ui::FOCUS }}" :class="m.author.id === {{ auth()->id() }} ? 'text-white/90' : 'text-[#B91C1C]'">Hapus</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <button type="button" x-show="newCount > 0" x-cloak @click="scrollBottom(); markRead()" class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow-lift transition hover:bg-brand-700">
                        ↓ Pesan baru (<span x-text="newCount"></span>)
                    </button>
                </div>

                <form x-show="state === 'ok'" @submit.prevent="send()" class="mt-4 space-y-2">
                    <p x-show="error" x-cloak class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-[#FEF2F2] p-3 text-sm font-semibold text-[#7F1D1D]" role="alert">
                        <span class="flex items-center gap-2"><x-lucide-triangle-alert class="h-4 w-4" aria-hidden="true" /><span x-text="error"></span></span>
                        <button type="button" x-show="failed" @click="send()" class="rounded-lg border border-[#FECACA] bg-white px-3 py-1 text-xs font-bold text-[#B91C1C] hover:bg-[#FEE2E2]">Kirim ulang</button>
                    </p>
                    <div x-show="files.length" class="flex flex-wrap gap-2">
                        <template x-for="(f, i) in files" :key="i">
                            <span class="inline-flex items-center gap-2 rounded-lg bg-ink-100 px-2.5 py-1 text-xs font-semibold text-ink-800"><span class="max-w-[10rem] truncate" x-text="f.name"></span><button type="button" @click="dropFile(i)" class="font-bold text-ink-600 hover:text-[#B91C1C]" :aria-label="'Hapus lampiran ' + f.name">×</button></span>
                        </template>
                    </div>
                    <div class="flex items-end gap-2">
                        <label class="grid h-11 w-11 shrink-0 cursor-pointer place-items-center rounded-xl border-2 border-ink-200 bg-white text-ink-700 hover:border-brand-400" title="Lampirkan berkas">
                            <x-lucide-paperclip class="h-5 w-5" aria-hidden="true" /><span class="sr-only">Lampirkan berkas (PNG, JPG, PDF)</span>
                            <input type="file" multiple accept=".png,.jpg,.jpeg,.pdf" @change="pickFiles($event)" class="sr-only">
                        </label>
                        <div class="flex-1">
                            <label for="chat-input" class="sr-only">Tulis pesan</label>
                            <textarea id="chat-input" x-ref="input" x-model="body" @keydown="onKey($event)" rows="2" placeholder="Tulis pesan… (Enter kirim, Shift+Enter baris baru)" class="w-full rounded-xl border-2 border-ink-200 bg-white px-3 py-2 text-sm {{ Ui::FOCUS }}"></textarea>
                            <p x-show="body.length > 1800" x-cloak class="text-right text-xs" :class="body.length > 2000 ? 'font-bold text-[#B91C1C]' : 'text-ink-500'" x-text="(2000 - body.length) + ' tersisa'"></p>
                        </div>
                        <button type="submit" :disabled="!canSend" class="{{ Ui::BTN_PRIMARY }} h-11 px-4 text-sm"><span x-text="sending ? 'Mengirim…' : 'Kirim'"></span></button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</main>
</x-layouts.site>
