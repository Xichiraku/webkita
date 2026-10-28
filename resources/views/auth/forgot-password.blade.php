<x-auth-shell :back="['/login', 'Kembali ke Login']" :step="0">
    <div class="flex flex-col items-center text-center">
        <span class="grid h-14 w-14 place-items-center rounded-full bg-brand-50 text-brand-600"><x-lucide-key-round class="h-6 w-6" aria-hidden="true" /></span>
        <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-ink-900">Lupa Password?</h1>
        <p class="mt-2 text-ink-600">Masukkan email akun Anda. Kami akan mengirim kode OTP 6 digit untuk mengatur ulang password.</p>
    </div>

    <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5" x-data="{ loading: false }" @submit="loading = true">
        @csrf
        <x-text-field id="email" label="Email" icon="mail" type="email" placeholder="nama@email.com" autocomplete="email" :value="old('email')" :error="$errors->first('email')" autofocus />

        <button type="submit" :disabled="loading" class="h-12 w-full text-[15px] {{ Ui::BTN_PRIMARY }}">
            <x-button-spinner x-show="loading" />
            <span x-text="loading ? 'Mengirim…' : 'Kirim Kode OTP'">Kirim Kode OTP</span>
        </button>
    </form>
</x-auth-shell>
