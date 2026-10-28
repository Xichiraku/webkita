<x-auth-shell :back="['/login', 'Kembali ke Login']" :step="2">
    <div class="flex flex-col items-center text-center">
        <span class="grid h-14 w-14 place-items-center rounded-full bg-brand-50 text-brand-600"><x-lucide-lock-keyhole class="h-6 w-6" aria-hidden="true" /></span>
        <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-ink-900">Password Baru</h1>
        <p class="mt-2 text-ink-600">Buat password baru untuk akun Anda. Minimal 8 karakter, mengandung huruf dan angka.</p>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="mt-8 space-y-5" x-data="{ loading: false }" @submit="loading = true">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ old('email', $email) }}">
        @error('email')<x-alert>{{ $message }}</x-alert>@enderror

        <x-password-input id="password" label="Password Baru" placeholder="Masukkan password baru" autocomplete="new-password" :error="$errors->first('password')" autofocus />
        <x-password-input id="password_confirmation" label="Konfirmasi Password" placeholder="Ulangi password baru" autocomplete="new-password" />

        <button type="submit" :disabled="loading" class="h-12 w-full text-[15px] {{ Ui::BTN_PRIMARY }}">
            <x-button-spinner x-show="loading" />
            <span x-text="loading ? 'Menyimpan…' : 'Ubah Password'">Ubah Password</span>
        </button>
    </form>
</x-auth-shell>
