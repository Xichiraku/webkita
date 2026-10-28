<x-auth-shell title="Buat Akun Baru" subtitle="Daftar untuk memesan dan memantau proyek website Anda." maxWidth="max-w-[32rem]">
    <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5" x-data="{ loading: false }" @submit="loading = true" novalidate>
        @csrf

        <x-text-field id="username" label="Username" icon="at-sign" type="text" placeholder="Contoh: budisudono" autocomplete="username" :value="old('username')" :error="$errors->first('username')" />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-text-field id="first_name" label="Nama Depan" icon="user" type="text" placeholder="Nama depan" autocomplete="given-name" :required="false" :value="old('first_name')" :error="$errors->first('first_name')" />
            <x-text-field id="last_name" label="Nama Belakang" icon="user" type="text" placeholder="Nama belakang" autocomplete="family-name" :required="false" :value="old('last_name')" :error="$errors->first('last_name')" />
        </div>

        <x-text-field id="email" label="Email" icon="mail" type="email" placeholder="nama@email.com" autocomplete="email" :value="old('email')" :error="$errors->first('email')" />

        <x-text-field id="phone" label="Nomor Telepon" icon="phone" type="tel" placeholder="08123456789" autocomplete="tel" :required="false" hint="opsional" :value="old('phone')" :error="$errors->first('phone')" />

        <x-password-input id="password" label="Password" placeholder="Minimal 8 karakter, huruf dan angka" autocomplete="new-password" :error="$errors->first('password')" />
        <x-password-input id="password_confirmation" label="Konfirmasi Password" placeholder="Ulangi password" autocomplete="new-password" />

        <label class="flex cursor-pointer items-start gap-3 rounded-2xl bg-ink-50 p-4">
            <input type="checkbox" name="subscribe_newsletter" value="1" @checked(old('subscribe_newsletter')) class="mt-0.5 h-5 w-5 shrink-0 rounded border-ink-400 accent-brand-600">
            <span class="text-sm text-ink-700">Berlangganan newsletter untuk update dan informasi terbaru.</span>
        </label>

        <button type="submit" :disabled="loading" class="h-12 w-full text-[15px] {{ Ui::BTN_PRIMARY }}">
            <x-button-spinner x-show="loading" />
            <span x-text="loading ? 'Memproses…' : 'Daftar'">Daftar</span>
        </button>
    </form>

    <x-slot:footer>
        <p class="mt-6 text-center text-sm text-ink-600">Sudah punya akun? <a href="{{ route('login') }}" class="font-bold text-brand-700 hover:underline">Masuk</a></p>
    </x-slot:footer>
</x-auth-shell>
