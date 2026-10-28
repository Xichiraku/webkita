@php
    $roles = ['user' => 'Pembeli', 'developer' => 'Developer', 'admin' => 'Admin'];
    $active = $user->isActive();
    $labelCls = 'block text-xs font-bold uppercase tracking-wide text-ink-600';
    $viewCls = 'mt-1.5 flex min-h-[2.5rem] items-center gap-2.5 text-[17px] text-ink-900';
    $fields = [
        ['username', 'Username', 'text', 'Contoh: budisudono', 'username'],
        ['first_name', 'Nama Depan', 'text', 'Nama depan', 'given-name'],
        ['last_name', 'Nama Belakang', 'text', 'Nama belakang', 'family-name'],
        ['phone', 'Nomor Telepon', 'tel', '08123456789', 'tel'],
    ];
@endphp
<x-layouts.site title="Profil">
<main class="relative overflow-hidden bg-gradient-to-b from-brand-50 to-ink-50" x-data="{ editing: {{ $errors->any() ? 'true' : 'false' }}, saving: false }"
      @keydown.escape.window="if (editing && !saving) editing = false">
    <div class="pointer-events-none absolute -left-24 top-20 h-72 w-72 animate-blob rounded-full bg-brand-100 opacity-70 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -right-24 top-96 h-72 w-72 animate-blob rounded-full bg-brand-200/50 blur-3xl" style="animation-delay: -7s" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-3xl px-5 pb-20 pt-10 sm:px-8 sm:pt-14">
        <div class="animate-rise">
            <h1 class="text-3xl font-extrabold tracking-tight text-ink-900 sm:text-4xl">Profil Pengguna</h1>
            <p class="mt-1.5 text-ink-600">Kelola informasi akun Anda.</p>
        </div>

        @if (session('status'))
            <p class="mt-6 flex items-start gap-2.5 rounded-xl bg-[#DCFCE7] p-3.5 text-sm font-semibold text-[#14532D]" role="status">
                <x-lucide-circle-check class="mt-0.5 h-5 w-5 shrink-0 text-[#15803D]" aria-hidden="true" /> {{ session('status') }}
            </p>
        @endif

        {{-- ===== Kartu identitas ===== --}}
        <section aria-label="Identitas akun" class="mt-8 animate-rise overflow-hidden rounded-3xl border border-ink-200 bg-white shadow-lift" style="animation-delay: 80ms">
            <div class="relative h-32 bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 sm:h-36">
                <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
                <div class="absolute bottom-0 left-1/3 h-24 w-24 rounded-full bg-brand-400/20 blur-2xl" aria-hidden="true"></div>
            </div>
            <div class="px-6 pb-7 sm:px-8">
                <div class="-mt-14 flex flex-col gap-4 sm:-mt-16 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:gap-5">
                        <span class="relative z-10 grid h-28 w-28 shrink-0 place-items-center rounded-full border-4 border-white bg-white text-brand-600 shadow-lift sm:h-32 sm:w-32">
                            <x-lucide-user class="h-14 w-14 sm:h-16 sm:w-16" stroke-width="1.75" aria-hidden="true" />
                        </span>
                        <div class="pb-1">
                            <h2 class="text-2xl font-extrabold tracking-tight text-ink-900">{{ $user->name }}</h2>
                            <p class="text-ink-600">{{ "@".$user->username }}</p>
                        </div>
                    </div>
                    <button x-show="!editing" type="button" @click="editing = true; $nextTick(() => document.getElementById('username').focus())" class="self-start px-5 py-3 text-sm sm:self-end {{ Ui::BTN_PRIMARY }}">
                        <x-lucide-square-pen class="h-4 w-4" aria-hidden="true" /> Edit Profil
                    </button>
                </div>
                <ul class="mt-5 flex flex-wrap gap-2" aria-label="Status akun">
                    <li class="inline-flex items-center gap-1.5 rounded-full bg-brand-100 px-3 py-1 text-xs font-bold text-brand-800"><x-lucide-shield class="h-3.5 w-3.5" aria-hidden="true" /> {{ $roles[$user->role->value] ?? 'Pembeli' }}</li>
                    <li class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold {{ $active ? 'bg-[#DCFCE7] text-[#15803D]' : 'bg-ink-100 text-ink-700' }}">
                        @if ($active)<x-lucide-circle-check class="h-3.5 w-3.5" aria-hidden="true" />@else<x-lucide-x class="h-3.5 w-3.5" aria-hidden="true" />@endif
                        {{ $active ? 'Aktif' : 'Tidak Aktif' }}
                    </li>
                </ul>
            </div>
        </section>

        {{-- ===== Informasi detail ===== --}}
        <form method="POST" action="{{ route('profile.update') }}" novalidate @submit="saving = true" class="mt-6 animate-rise rounded-3xl border border-ink-200 bg-white p-6 shadow-soft sm:p-8" style="animation-delay: 140ms" aria-labelledby="detail-title">
            @csrf
            @method('PATCH')
            <h2 id="detail-title" class="flex items-center gap-2.5 text-xl font-extrabold tracking-tight text-ink-900">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-100 text-brand-700"><x-lucide-user class="h-5 w-5" aria-hidden="true" /></span> Informasi Detail
            </h2>

            <div class="mt-7 grid gap-x-8 gap-y-5 sm:grid-cols-2">
                @foreach ($fields as [$name, $label, $type, $placeholder, $auto])
                    <div>
                        <label for="{{ $name }}" class="{{ $labelCls }}">{{ $label }}</label>
                        <template x-if="!editing">
                            <p class="{{ $viewCls }}">
                                @if ($name === 'username')<x-lucide-at-sign class="h-[18px] w-[18px] shrink-0 text-ink-500" aria-hidden="true" />@endif
                                @if ($name === 'phone')<x-lucide-phone class="h-[18px] w-[18px] shrink-0 text-ink-500" aria-hidden="true" />@endif
                                <span class="break-all">{{ $user->{$name} ?: '-' }}</span>
                            </p>
                        </template>
                        <div x-show="editing" x-cloak class="mt-1.5">
                            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $user->{$name}) }}" placeholder="{{ $placeholder }}" autocomplete="{{ $auto }}"
                                   @error($name) aria-invalid="true" @enderror aria-describedby="{{ $name }}-err" class="{{ Ui::INPUT }}">
                            <x-field-error :id="$name.'-err'" :messages="$errors->get($name)" />
                            @if ($name === 'username' && ! $errors->has('username'))
                                <p class="mt-1.5 text-sm text-ink-600">3-30 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.</p>
                            @endif
                        </div>
                    </div>
                    @if ($name === 'username')
                        <div>
                            <p class="{{ $labelCls }}">Email</p>
                            <p class="{{ $viewCls }}"><x-lucide-mail class="h-[18px] w-[18px] shrink-0 text-ink-500" aria-hidden="true" /><span class="break-all">{{ $user->email }}</span></p>
                            <p x-show="editing" x-cloak class="mt-1 text-sm text-ink-600">Email tidak dapat diubah dari sini.</p>
                        </div>
                    @endif
                @endforeach

                <div>
                    <p class="{{ $labelCls }}">Tanggal Bergabung</p>
                    <p class="{{ $viewCls }}"><x-lucide-calendar class="h-[18px] w-[18px] shrink-0 text-ink-500" aria-hidden="true" />{{ Ui::dateLong($user->created_at) }}</p>
                </div>
            </div>

            <label class="mt-6 flex items-start gap-3 rounded-2xl bg-ink-50 p-4" :class="editing ? 'cursor-pointer' : 'cursor-default'">
                <input type="checkbox" name="subscribe_newsletter" value="1" :disabled="!editing" @checked(old('subscribe_newsletter', $user->subscribe_newsletter)) class="mt-1 h-5 w-5 shrink-0 rounded border-ink-400 accent-brand-600 disabled:opacity-100">
                <span>
                    <span class="flex items-center gap-2 font-bold text-ink-900"><x-lucide-bell class="h-4 w-4 text-ink-600" aria-hidden="true" /> Subscribe Newsletter</span>
                    <span class="mt-0.5 block text-sm text-ink-600">Terima update dan informasi terbaru via email.</span>
                </span>
            </label>

            <div x-show="editing" x-cloak class="mt-7 flex flex-col gap-3 border-t border-ink-200 pt-6 sm:flex-row">
                <button type="submit" :disabled="saving" class="px-6 py-3 disabled:cursor-wait {{ Ui::BTN_PRIMARY }}">
                    <x-button-spinner x-show="saving" /><x-lucide-save x-show="!saving" class="h-4 w-4" aria-hidden="true" />
                    <span x-text="saving ? 'Menyimpan…' : 'Simpan Perubahan'"></span>
                </button>
                <a href="{{ route('profile.edit') }}" class="px-6 py-3 {{ Ui::BTN_SECONDARY }}"><x-lucide-x class="h-4 w-4" aria-hidden="true" /> Batal</a>
            </div>
        </form>

        {{-- ===== Keamanan ===== --}}
        <section aria-labelledby="sec-title" class="mt-6 animate-rise rounded-3xl border border-ink-200 bg-white p-6 shadow-soft sm:p-8" style="animation-delay: 200ms">
            <h2 id="sec-title" class="flex items-center gap-2.5 text-xl font-extrabold tracking-tight text-ink-900">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-brand-100 text-brand-700"><x-lucide-lock class="h-5 w-5" aria-hidden="true" /></span> Keamanan Akun
            </h2>
            <div class="mt-6 flex flex-col gap-4 rounded-2xl bg-ink-50 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="font-bold text-ink-900">Password</p>
                    <p class="mt-0.5 text-ink-600">Reset lewat halaman "Lupa Password".</p>
                </div>
                <a href="{{ route('password.request') }}" class="px-5 py-2.5 text-sm {{ Ui::BTN_SECONDARY }}">Reset Password <x-lucide-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
            </div>
        </section>
    </div>
</main>
</x-layouts.site>
