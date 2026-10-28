<x-auth-shell title="Masuk ke Akun" subtitle="Pantau proyek website Anda di Project Workspace.">
    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5" x-data="{ loading: false }" @submit="loading = true">
        @csrf

        @if (session('status'))
            <x-alert type="success">{{ session('status') }}</x-alert>
        @endif
        @if ($errors->has('login') && ! $errors->has('password'))
            <x-alert>{{ $errors->first('login') }}</x-alert>
        @endif

        <x-text-field id="login" label="Email atau Username" icon="mail" type="text" placeholder="nama@email.com atau username" autocomplete="username"
                      :value="old('login')" autofocus />

        <x-password-input id="password" label="Password" placeholder="Masukkan password" autocomplete="current-password" :error="$errors->first('password')" />

        <div class="flex items-center justify-between">
            <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-ink-700">
                <input type="checkbox" name="remember" class="h-4 w-4 rounded border-ink-400 accent-brand-600"> Ingat saya
            </label>
            <a href="{{ route('password.request') }}" class="rounded text-sm font-semibold text-brand-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-400">Lupa password?</a>
        </div>

        <button type="submit" :disabled="loading" class="h-12 w-full text-[15px] {{ Ui::BTN_PRIMARY }}">
            <x-button-spinner x-show="loading" />
            <span x-text="loading ? 'Memproses…' : 'Masuk'">Masuk</span>
        </button>
    </form>

    <x-slot:footer>
        <p class="mt-6 text-center text-sm text-ink-600">Belum punya akun? <a href="{{ route('register') }}" class="font-bold text-brand-700 hover:underline">Daftar sekarang</a></p>
    </x-slot:footer>
</x-auth-shell>
