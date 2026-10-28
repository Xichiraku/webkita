@php
    $steps = [['Informasi Dasar', 'clipboard-list'], ['Skill & Lampiran', 'wrench'], ['Kontak', 'phone']];
    $suggest = ['Laravel', 'WordPress', 'React', 'Node.js', 'MongoDB', 'Tailwind CSS', 'Serahkan ke developer'];
    $user = auth()->user();
@endphp
<x-layouts.site title="Informasi Detail Proyek">
<script>
document.addEventListener('alpine:init', () => {
    const DRAFT_KEY = 'webkita_pesan_draft';
    const STEPS = 3;
    const FIELDS = [['projectTitle', 'category', 'description'], ['skills'], ['contactName', 'contactPhone']];
    const PHONE_RE = /^(\+62|62|0)8[1-9][0-9]{6,11}$/;
    const OK_FILE = /\.(pdf|png|jpe?g)$/i;
    const MAX_FILE = 10 * 1024 * 1024;

    Alpine.data('orderWizard', (plan, defaults) => ({
        plan, step: 0, loading: false, done: false, error: '', errors: {}, files: [], fileErr: '', skillInput: '',
        skillStatus: '', drag: false, form: { projectTitle: '', category: '', description: '', skills: [], additionalNotes: '', ...defaults },
        init() {
            try {
                const d = JSON.parse(sessionStorage.getItem(DRAFT_KEY) || 'null') || {};
                for (const k of Object.keys(this.form)) if (d[k] !== undefined && d[k] !== '' && !(Array.isArray(d[k]) && !d[k].length)) this.form[k] = d[k];
            } catch (e) { /* abaikan */ }
            this.$watch('form', (f) => { try { sessionStorage.setItem(DRAFT_KEY, JSON.stringify(f)); } catch (e) { /* abaikan */ } }, { deep: true });
            this.$watch('step', () => this.$nextTick(() => {
                const h = document.getElementById(`h-${this.step}`); h?.focus({ preventScroll: true }); h?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }));
        },
        get descLen() { return this.form.description.trim().length; },
        validate(step = this.step) {
            const f = this.form, e = {};
            if (step === 0) {
                const t = f.projectTitle.trim(), d = f.description.trim();
                if (!t) e.projectTitle = 'Judul proyek wajib diisi.'; else if (t.length < 5) e.projectTitle = 'Judul minimal 5 karakter.';
                if (!f.category) e.category = 'Pilih kategori proyek.';
                if (!d) e.description = 'Deskripsi proyek wajib diisi.';
                else if (d.length < 100) e.description = `Deskripsi minimal 100 karakter (kurang ${100 - d.length} lagi).`;
            }
            if (step === 1 && !f.skills.length) e.skills = 'Tambahkan minimal satu skill, atau pilih "Serahkan ke developer".';
            if (step === 2) {
                const p = f.contactPhone.replace(/[\s-]/g, '');
                if (!f.contactName.trim()) e.contactName = 'Nama lengkap wajib diisi.';
                if (!p) e.contactPhone = 'Nomor WhatsApp wajib diisi.';
                else if (!PHONE_RE.test(p)) e.contactPhone = 'Gunakan nomor Indonesia, contoh 08123456789.';
            }
            return e;
        },
        get stepOk() { return Object.keys(this.validate()).length === 0; },
        touch(k) { if (this.errors[k]) this.errors[k] = this.validate()[k]; },
        shake() { const el = this.$refs.form; el.classList.remove('shake'); void el.offsetWidth; el.classList.add('shake'); },
        addSkill(raw) {
            const v = raw.trim().replace(/\s+/g, ' ');
            if (!v) return;
            if (this.form.skills.some((x) => x.toLowerCase() === v.toLowerCase())) { this.skillStatus = `${v} sudah ditambahkan.`; return; }
            this.form.skills.push(v);
            delete this.errors.skills;
            this.skillInput = '';
            this.skillStatus = `${v} ditambahkan. Total ${this.form.skills.length} skill.`;
        },
        removeSkill(i) { this.skillStatus = `${this.form.skills[i]} dihapus.`; this.form.skills.splice(i, 1); this.$refs.skill.focus(); },
        addFiles(list) {
            const bad = [];
            Array.from(list || []).forEach((f) => {
                if (!OK_FILE.test(f.name)) bad.push(`${f.name}: format harus PDF, PNG, atau JPG`);
                else if (f.size > MAX_FILE) bad.push(`${f.name}: ukuran melebihi 10MB`);
                else if (!this.files.some((x) => x.name === f.name && x.size === f.size)) this.files.push(f);
            });
            this.fileErr = bad.join('. ');
        },
        size(b) { return b < 1048576 ? Math.max(1, Math.round(b / 1024)) + ' KB' : (b / 1048576).toFixed(1).replace('.', ',') + ' MB'; },
        prev() { this.step === 0 ? (window.location.href = @js(route('harga'))) : this.step--; },
        async next() {
            if (this.loading) return;
            const e = this.validate();
            if (Object.keys(e).length) {
                this.errors = e;
                const first = FIELDS[this.step].find((f) => e[f]);
                document.getElementById(first === 'skills' ? 'skill-input' : first)?.focus();
                this.shake();
                return;
            }
            this.errors = {};
            if (this.step < STEPS - 1) { this.step++; return; }
            await this.submit();
        },
        async submit() {
            this.loading = true; this.error = '';
            const f = this.form;
            try {
                const res = await api('POST', '/api/projects', {
                    plan_title: this.plan?.title ?? '', price_range: this.plan?.priceRange ?? '',
                    project_title: f.projectTitle.trim(), category: f.category, description: f.description.trim(),
                    skills: f.skills.join(', '), contact_name: f.contactName.trim(),
                    contact_phone: f.contactPhone.replace(/[\s-]/g, ''), additional_notes: f.additionalNotes.trim(),
                });
                const id = res.data.id;
                const failed = [];
                for (const file of this.files) {
                    const fd = new FormData(); fd.append('file', file);
                    try { await api('POST', `/api/attachments/${id}`, fd); } catch (err) { failed.push(file.name); }
                }
                try { sessionStorage.removeItem(DRAFT_KEY); } catch (e2) { /* abaikan */ }
                if (failed.length) Alpine.store('toast').show(`Pengajuan terkirim, tetapi lampiran gagal diunggah: ${failed.join(', ')}`, 'err');
                this.done = true;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (err) {
                this.error = err.message; this.shake();
            } finally { this.loading = false; }
        },
    }));
});
</script>

<main class="relative overflow-hidden bg-ink-50"
      x-data="orderWizard(@js($plan), @js(['contactName' => $user->name, 'contactPhone' => $user->phone]))">
    <div class="pointer-events-none absolute -left-24 top-0 h-72 w-72 animate-blob rounded-full bg-brand-100 opacity-70 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -right-24 top-80 h-72 w-72 animate-blob rounded-full bg-brand-200/50 blur-3xl" style="animation-delay: -7s" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-3xl px-5 pb-20 pt-8 sm:px-8 sm:pt-10">
        <a href="{{ route('harga') }}" class="group inline-flex items-center gap-2 rounded-md text-sm font-semibold text-ink-700 transition hover:text-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-400">
            <x-lucide-arrow-left class="h-4 w-4 transition-transform group-hover:-translate-x-1" aria-hidden="true" /> Kembali ke Halaman Harga
        </a>

        <div class="mt-6 animate-rise text-center">
            <h1 class="text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">Informasi Detail Proyek</h1>
            <p class="mt-2 text-ink-600">Lengkapi informasi proyek Anda untuk mendapatkan penawaran terbaik.</p>
            <div class="mt-4 inline-flex">
                <template x-if="plan">
                    <span class="inline-flex flex-wrap items-center justify-center gap-x-2 rounded-full bg-brand-100 px-4 py-2 text-sm font-bold text-brand-800">
                        <x-lucide-package-check class="h-4 w-4" aria-hidden="true" />
                        <span x-text="`Paket Terpilih: ${plan.title} (${plan.priceRange})`"></span>
                        <a x-show="!done" href="{{ route('harga') }}" class="rounded font-semibold text-brand-700 underline underline-offset-2 hover:text-brand-900">Ganti</a>
                    </span>
                </template>
                <template x-if="!plan">
                    <a href="{{ route('harga') }}" class="inline-flex items-center gap-2 rounded-full bg-[#FEF3C7] px-4 py-2 text-sm font-bold text-[#92400E] hover:bg-[#FDE68A]">
                        <x-lucide-triangle-alert class="h-4 w-4" aria-hidden="true" /> Belum memilih paket. Pilih paket dulu
                    </a>
                </template>
            </div>
        </div>

        {{-- ===== Berhasil ===== --}}
        <div x-show="done" x-cloak class="step-in mt-8 rounded-3xl border border-ink-200 bg-white px-6 py-14 text-center shadow-lift sm:px-12">
            <span class="mx-auto grid h-20 w-20 animate-pop place-items-center rounded-full bg-[#DCFCE7] text-[#15803D]"><x-lucide-check class="h-10 w-10" aria-hidden="true" /></span>
            <h2 class="mt-6 text-2xl font-extrabold tracking-tight sm:text-3xl">Pengajuan Terkirim</h2>
            <p class="mx-auto mt-2 max-w-md text-ink-600">Terima kasih! Kami akan menghubungi Anda melalui WhatsApp. Status pengajuan bisa dipantau di halaman Orderan.</p>
            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <a href="{{ route('orderan') }}" class="px-6 py-3 {{ Ui::BTN_PRIMARY }}">Lihat Orderan Saya <x-lucide-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
                <a href="{{ url('/') }}" class="px-6 py-3 {{ Ui::BTN_SECONDARY }}">Kembali ke Beranda</a>
            </div>
        </div>

        <div x-show="!done">
            {{-- ===== Stepper ===== --}}
            <nav aria-label="Langkah pengisian" class="mt-8 rounded-2xl border border-ink-200 bg-white px-4 py-5 shadow-soft sm:px-8">
                <ol class="flex items-start">
                    @foreach ($steps as $i => [$label, $icon])
                        <li class="flex flex-col items-center">
                            <button type="button" @click="if ({{ $i }} < step) step = {{ $i }}" :tabindex="{{ $i }} < step ? 0 : -1"
                                    :aria-current="step === {{ $i }} ? 'step' : null"
                                    class="flex w-20 flex-col items-center gap-2 rounded-xl text-center focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand-400 sm:w-28"
                                    :class="{{ $i }} < step ? 'cursor-pointer' : 'cursor-default'">
                                <span class="grid h-12 w-12 place-items-center rounded-full transition-all duration-300"
                                      :class="step === {{ $i }} ? 'bg-brand-600 text-white shadow-soft ring-4 ring-brand-100' : ({{ $i }} < step ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-600')">
                                    <x-lucide-check x-show="{{ $i }} < step" class="h-5 w-5" aria-hidden="true" />
                                    <x-dynamic-component x-show="{{ $i }} >= step" :component="'lucide-'.$icon" class="h-5 w-5" aria-hidden="true" />
                                </span>
                                <span class="text-xs font-bold leading-tight sm:text-sm" :class="step >= {{ $i }} ? 'text-brand-700' : 'text-ink-600'">{{ $label }}</span>
                            </button>
                        </li>
                        @if (! $loop->last)
                            <li aria-hidden="true" class="mt-[22px] h-1 flex-1 overflow-hidden rounded-full bg-ink-200">
                                <div class="h-full rounded-full bg-brand-600 transition-all duration-500" :style="`width: ${ {{ $i }} < step ? '100%' : '0%' }`"></div>
                            </li>
                        @endif
                    @endforeach
                </ol>
            </nav>

            <form x-ref="form" novalidate @submit.prevent="next()" class="mt-6 rounded-3xl border border-ink-200 bg-white p-6 shadow-lift sm:p-9">
                <div x-show="error" x-cloak class="mb-6 flex items-start gap-3 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm text-[#7F1D1D]" role="alert">
                    <x-lucide-circle-alert class="mt-0.5 h-5 w-5 shrink-0 text-[#B91C1C]" aria-hidden="true" />
                    <div class="flex-1"><p class="font-bold">Gagal mengirim pengajuan</p><p class="mt-0.5" x-text="error"></p></div>
                    <button type="button" @click="error = ''" class="grid h-8 w-8 place-items-center rounded-lg text-[#B91C1C] hover:bg-[#FEE2E2]" aria-label="Tutup pesan error"><x-lucide-x class="h-4 w-4" aria-hidden="true" /></button>
                </div>

                {{-- ===== LANGKAH 1 ===== --}}
                <section x-show="step === 0" class="step-in" aria-labelledby="h-0">
                    <h2 id="h-0" tabindex="-1" class="text-2xl font-extrabold tracking-tight outline-none">Informasi Dasar</h2>
                    <p class="mt-1.5 text-ink-600">Berikan informasi dasar tentang proyek Anda.</p>
                    <div class="mt-7 space-y-6">
                        <div>
                            <label for="projectTitle" class="{{ Ui::LABEL }}">Judul Proyek <span class="text-[#B91C1C]" aria-hidden="true">*</span></label>
                            <input id="projectTitle" x-model="form.projectTitle" @input="touch('projectTitle')" type="text" maxlength="100" placeholder="Contoh: Website E-commerce untuk Fashion" autocomplete="off"
                                   :aria-invalid="errors.projectTitle ? 'true' : null" aria-describedby="projectTitle-err" class="{{ Ui::INPUT }}">
                            <p id="projectTitle-err" x-show="errors.projectTitle" class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold text-[#B91C1C]" role="alert"><x-lucide-circle-alert class="h-4 w-4 shrink-0" aria-hidden="true" /><span x-text="errors.projectTitle"></span></p>
                        </div>
                        <div>
                            <label for="category" class="{{ Ui::LABEL }}">Kategori <span class="text-[#B91C1C]" aria-hidden="true">*</span></label>
                            <div class="relative">
                                <select id="category" x-model="form.category" @change="touch('category')" :aria-invalid="errors.category ? 'true' : null" aria-describedby="category-err"
                                        class="{{ Ui::INPUT }} appearance-none pr-11" :class="form.category ? '' : 'text-ink-500'">
                                    <option value="" disabled>Pilih kategori proyek</option>
                                    @foreach (Ui::CATEGORIES as $value => $label)
                                        <option value="{{ $value }}" class="text-ink-900">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-lucide-chevron-down class="pointer-events-none absolute right-4 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-500" aria-hidden="true" />
                            </div>
                            <p id="category-err" x-show="errors.category" class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold text-[#B91C1C]" role="alert"><x-lucide-circle-alert class="h-4 w-4 shrink-0" aria-hidden="true" /><span x-text="errors.category"></span></p>
                        </div>
                        <div>
                            <label for="description" class="{{ Ui::LABEL }}">Deskripsi Proyek <span class="text-[#B91C1C]" aria-hidden="true">*</span></label>
                            <textarea id="description" x-model="form.description" @input="touch('description')" rows="6" aria-describedby="description-count description-err"
                                      :aria-invalid="errors.description ? 'true' : null"
                                      placeholder="Jelaskan secara detail kebutuhan proyek Anda, fitur yang diinginkan, target audience, dan ekspektasi hasil..."
                                      class="{{ Ui::INPUT }} h-auto min-h-[10rem] resize-y py-3 leading-relaxed"></textarea>
                            <div class="mt-1.5 flex items-center justify-between gap-3">
                                <p id="description-count" class="text-sm" :class="descLen >= 100 ? 'text-[#15803D]' : 'text-ink-600'" x-text="`Minimal 100 karakter. (${descLen}/100)`"></p>
                                <span x-show="descLen >= 100" class="inline-flex items-center gap-1 text-sm font-semibold text-[#15803D]"><x-lucide-circle-check class="h-4 w-4" aria-hidden="true" /> Cukup</span>
                            </div>
                            <p id="description-err" x-show="errors.description" class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold text-[#B91C1C]" role="alert"><x-lucide-circle-alert class="h-4 w-4 shrink-0" aria-hidden="true" /><span x-text="errors.description"></span></p>
                        </div>
                    </div>
                </section>

                {{-- ===== LANGKAH 2 ===== --}}
                <section x-show="step === 1" x-cloak class="step-in" aria-labelledby="h-1">
                    <h2 id="h-1" tabindex="-1" class="text-2xl font-extrabold tracking-tight outline-none">Skill &amp; Lampiran</h2>
                    <p class="mt-1.5 text-ink-600">Tambahkan skill atau teknologi yang dibutuhkan untuk proyek ini.</p>
                    <div class="mt-7 space-y-8">
                        <div>
                            <label for="skill-input" class="mb-1 block text-sm font-bold text-ink-800">Skill yang Dibutuhkan <span class="text-[#B91C1C]" aria-hidden="true">*</span></label>
                            <p id="skill-hint" class="mb-2 text-sm text-ink-600">Ketik lalu tekan Enter atau tombol Tambah. Belum tahu? Pilih "Serahkan ke developer".</p>
                            <div class="flex gap-3">
                                <input id="skill-input" x-ref="skill" x-model="skillInput" @keydown.enter.prevent="addSkill(skillInput)" type="text" maxlength="30"
                                       placeholder="Contoh: React, Node.js, MongoDB" autocomplete="off" aria-describedby="skill-hint skills-err"
                                       :aria-invalid="errors.skills ? 'true' : null" class="{{ Ui::INPUT }} min-w-0 flex-1">
                                <button type="button" @click="addSkill(skillInput); $refs.skill.focus()" class="h-12 shrink-0 px-5 {{ Ui::BTN_PRIMARY }}"><x-lucide-plus class="h-5 w-5" aria-hidden="true" /> Tambah</button>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2" aria-label="Saran skill">
                                <span class="text-sm text-ink-600">Saran:</span>
                                @foreach ($suggest as $s)
                                    <button type="button" x-show="!form.skills.some((x) => x.toLowerCase() === @js(strtolower($s)))" @click="addSkill(@js($s))"
                                            class="rounded-full border border-ink-300 bg-white px-3 py-1 text-sm font-semibold text-ink-700 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700">+ {{ $s }}</button>
                                @endforeach
                            </div>
                            <ul class="mt-4 flex flex-wrap gap-2" aria-label="Skill yang dipilih">
                                <template x-for="(s, i) in form.skills" :key="s">
                                    <li class="step-in inline-flex items-center gap-1.5 rounded-full bg-brand-100 py-1.5 pl-3.5 pr-1.5 text-sm font-semibold text-brand-800">
                                        <span x-text="s"></span>
                                        <button type="button" @click="removeSkill(i)" class="grid h-6 w-6 place-items-center rounded-full text-brand-700 transition hover:bg-brand-200" :aria-label="`Hapus skill ${s}`"><x-lucide-x class="h-3.5 w-3.5" aria-hidden="true" /></button>
                                    </li>
                                </template>
                            </ul>
                            <p class="sr-only" role="status" aria-live="polite" x-text="skillStatus"></p>
                            <p id="skills-err" x-show="errors.skills" class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold text-[#B91C1C]" role="alert"><x-lucide-circle-alert class="h-4 w-4 shrink-0" aria-hidden="true" /><span x-text="errors.skills"></span></p>
                        </div>

                        <div>
                            <p class="mb-1 text-sm font-bold text-ink-800">Lampiran <span class="font-normal text-ink-600">(opsional)</span></p>
                            <p id="file-hint" class="mb-2 text-sm text-ink-600">Upload dokumen pendukung seperti wireframe, mockup, atau requirement document.</p>
                            <label for="files"
                                   @dragenter.prevent="drag = true" @dragover.prevent="drag = true" @dragleave.prevent="drag = false" @drop.prevent="drag = false; addFiles($event.dataTransfer.files)"
                                   class="flex cursor-pointer flex-col items-center rounded-2xl border-2 border-dashed px-6 py-9 text-center transition hover:border-brand-400 hover:bg-brand-50 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-300"
                                   :class="drag ? 'border-brand-500 bg-brand-50' : 'border-ink-300 bg-white'">
                                <span class="grid h-12 w-12 place-items-center rounded-xl bg-brand-100 text-brand-700"><x-lucide-upload class="h-6 w-6" aria-hidden="true" /></span>
                                <span class="mt-3 font-bold text-ink-900">Klik untuk upload file <span class="font-normal text-ink-600">atau seret ke sini</span></span>
                                <span class="mt-1 text-sm text-ink-600">PDF, PNG, JPG (Maks 10MB per file)</span>
                                <input id="files" type="file" multiple accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg" class="sr-only" aria-describedby="file-hint files-err"
                                       @change="addFiles($event.target.files); $event.target.value = ''">
                            </label>
                            <p id="files-err" x-show="fileErr" class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold text-[#B91C1C]" role="alert"><x-lucide-circle-alert class="h-4 w-4 shrink-0" aria-hidden="true" /><span x-text="fileErr"></span></p>
                            <ul class="mt-4 space-y-2">
                                <template x-for="(f, i) in files" :key="f.name + f.size">
                                    <li class="step-in flex items-center gap-3 rounded-xl border border-ink-200 bg-white p-3">
                                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700"><x-lucide-file-text class="h-5 w-5" aria-hidden="true" /></span>
                                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold text-ink-900" x-text="f.name"></span><span class="text-xs text-ink-600" x-text="size(f.size)"></span></span>
                                        <button type="button" @click="files.splice(i, 1); fileErr = ''" class="grid h-9 w-9 place-items-center rounded-lg text-ink-600 transition hover:bg-[#FEE2E2] hover:text-[#B91C1C]" :aria-label="`Hapus file ${f.name}`"><x-lucide-trash-2 class="h-4 w-4" aria-hidden="true" /></button>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </section>

                {{-- ===== LANGKAH 3 ===== --}}
                <section x-show="step === 2" x-cloak class="step-in" aria-labelledby="h-2">
                    <h2 id="h-2" tabindex="-1" class="text-2xl font-extrabold tracking-tight outline-none">Informasi Kontak</h2>
                    <p class="mt-1.5 text-ink-600">Kami akan menghubungi Anda melalui informasi ini.</p>
                    <div class="mt-7 space-y-6">
                        <div>
                            <label for="contactName" class="{{ Ui::LABEL }}">Nama Lengkap <span class="text-[#B91C1C]" aria-hidden="true">*</span></label>
                            <input id="contactName" x-model="form.contactName" @input="touch('contactName')" type="text" placeholder="Masukkan nama lengkap Anda" autocomplete="name" :aria-invalid="errors.contactName ? 'true' : null" aria-describedby="contactName-err" class="{{ Ui::INPUT }}">
                            <p id="contactName-err" x-show="errors.contactName" class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold text-[#B91C1C]" role="alert"><x-lucide-circle-alert class="h-4 w-4 shrink-0" aria-hidden="true" /><span x-text="errors.contactName"></span></p>
                        </div>
                        <div>
                            <label for="contactPhone" class="{{ Ui::LABEL }}">Nomor WhatsApp <span class="text-[#B91C1C]" aria-hidden="true">*</span></label>
                            <input id="contactPhone" x-model="form.contactPhone" @input="touch('contactPhone')" type="tel" inputmode="tel" placeholder="08xxxxxxxxxx" autocomplete="tel" :aria-invalid="errors.contactPhone ? 'true' : null" aria-describedby="contactPhone-err" class="{{ Ui::INPUT }}">
                            <p id="contactPhone-err" x-show="errors.contactPhone" class="mt-1.5 flex items-center gap-1.5 text-sm font-semibold text-[#B91C1C]" role="alert"><x-lucide-circle-alert class="h-4 w-4 shrink-0" aria-hidden="true" /><span x-text="errors.contactPhone"></span></p>
                        </div>
                        <div>
                            <label for="additionalNotes" class="{{ Ui::LABEL }}">Catatan Tambahan</label>
                            <textarea id="additionalNotes" x-model="form.additionalNotes" rows="4" placeholder="Ada hal lain yang ingin Anda sampaikan?" class="{{ Ui::INPUT }} h-auto resize-y py-3 leading-relaxed"></textarea>
                        </div>

                        <dl class="grid gap-x-6 gap-y-3 rounded-2xl bg-ink-50 p-5 text-sm sm:grid-cols-2" aria-label="Ringkasan pengajuan">
                            <div><dt class="font-semibold text-ink-600">Paket</dt><dd class="font-bold text-ink-900" x-text="plan?.title || '-'"></dd></div>
                            <div><dt class="font-semibold text-ink-600">Kategori</dt><dd class="font-bold text-ink-900" x-text="$refs.form.querySelector('#category')?.selectedOptions[0]?.textContent?.trim() || '-'"></dd></div>
                            <div class="sm:col-span-2"><dt class="font-semibold text-ink-600">Judul proyek</dt><dd class="break-words font-bold text-ink-900" x-text="form.projectTitle.trim() || '-'"></dd></div>
                            <div><dt class="font-semibold text-ink-600">Skill</dt><dd class="font-bold text-ink-900" x-text="form.skills.length ? form.skills.join(', ') : '-'"></dd></div>
                            <div><dt class="font-semibold text-ink-600">Lampiran</dt><dd class="font-bold text-ink-900" x-text="files.length ? files.length + ' file' : 'Tidak ada'"></dd></div>
                        </dl>

                        <p class="flex items-start gap-2.5 rounded-xl bg-[#DCFCE7] p-4 text-sm text-[#14532D]">
                            <x-lucide-circle-check class="mt-0.5 h-5 w-5 shrink-0 text-[#15803D]" aria-hidden="true" />
                            <span><strong>Hampir selesai!</strong> Pastikan semua informasi sudah benar sebelum mengirim.</span>
                        </p>
                    </div>
                </section>

                {{-- ===== Navigasi ===== --}}
                <div class="mt-9 flex items-center justify-between gap-3 border-t border-ink-200 pt-6">
                    <button type="button" @click="prev()" :disabled="loading" class="px-6 py-3 {{ Ui::BTN_SECONDARY }}" x-text="step === 0 ? 'Kembali' : 'Sebelumnya'"></button>
                    <button type="submit" :aria-disabled="!stepOk || loading"
                            class="group px-6 py-3 {{ Ui::BTN_PRIMARY }} aria-[disabled=true]:cursor-not-allowed aria-[disabled=true]:bg-ink-200 aria-[disabled=true]:text-ink-600 aria-[disabled=true]:shadow-none aria-[disabled=true]:hover:translate-y-0">
                        <x-button-spinner x-show="loading" />
                        <span x-text="loading ? 'Mengirim…' : (step === 2 ? 'Kirim Pengajuan' : 'Selanjutnya')"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>
</x-layouts.site>
