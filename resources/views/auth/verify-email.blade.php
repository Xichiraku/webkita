<x-auth-shell :back="['/login', 'Kembali ke Login']">
    <div class="flex flex-col items-center text-center">
        <span class="grid h-16 w-16 place-items-center rounded-full bg-brand-50 text-brand-600"><x-lucide-mail-check class="h-8 w-8" aria-hidden="true" /></span>
        <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-ink-900">Cek Email Anda</h1>
        <p class="mt-2 text-ink-600">
            Kami telah mengirim tautan verifikasi ke email Anda. Klik tautan tersebut untuk mengaktifkan akun
            (berlaku {{ \App\Models\User::VERIFICATION_TTL_HOURS }} jam).
        </p>
    </div>

    <form method="POST" action="{{ route('verification.send') }}" class="mt-8 space-y-5" x-data="{ loading: false }" @submit="loading = true">
        @csrf

        @if (session('status'))
            <x-alert type="success">{{ session('status') }}</x-alert>
        @endif

        <x-text-field id="email" label="Email" icon="mail" type="email" placeholder="nama@email.com" :value="old('email', $email)" :error="$errors->first('email')" />

        <p class="text-center text-sm text-ink-600">Belum menerima email? Periksa folder spam atau kirim ulang.</p>

        <button type="submit" :disabled="loading" class="h-12 w-full text-[15px] {{ Ui::BTN_PRIMARY }}">
            <x-button-spinner x-show="loading" />
            <span x-text="loading ? 'Mengirim…' : 'Kirim Ulang Verifikasi'">Kirim Ulang Verifikasi</span>
        </button>
    </form>
</x-auth-shell>
