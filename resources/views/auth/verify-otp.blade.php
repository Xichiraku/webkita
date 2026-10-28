<x-auth-shell :back="[route('password.request'), 'Ganti Email']" :step="1">
    <div class="flex flex-col items-center text-center">
        <span class="grid h-14 w-14 place-items-center rounded-full bg-brand-50 text-brand-600"><x-lucide-shield-check class="h-6 w-6" aria-hidden="true" /></span>
        <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-ink-900">Masukkan Kode OTP</h1>
        <p class="mt-2 text-ink-600">Kode 6 digit dikirim ke <strong class="break-all text-ink-800">{{ $email ?: 'email Anda' }}</strong>. Kode berlaku {{ \App\Http\Controllers\Auth\PasswordResetLinkController::OTP_TTL_MINUTES }} menit.</p>
    </div>

    <form method="POST" action="{{ route('password.otp.verify') }}" class="mt-8 space-y-5" x-data="{ loading: false }" @submit="loading = true">
        @csrf

        @if (session('status'))
            <x-alert type="success">{{ session('status') }}</x-alert>
        @endif

        <input type="hidden" name="email" value="{{ old('email', $email) }}">
        @error('email')<x-alert>{{ $message }}</x-alert>@enderror

        <div>
            <label for="otp" class="{{ Ui::LABEL }}">Kode OTP <span class="text-[#B91C1C]" aria-hidden="true">*</span></label>
            <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" autofocus placeholder="000000"
                   x-on:input="$el.value = $el.value.replace(/\D/g, '')" @error('otp') aria-invalid="true" @enderror aria-describedby="otp-err"
                   class="{{ Ui::INPUT }} text-center text-2xl font-bold tracking-[.5em]">
            <x-field-error id="otp-err" :messages="$errors->get('otp')" />
        </div>

        <button type="submit" :disabled="loading" class="h-12 w-full text-[15px] {{ Ui::BTN_PRIMARY }}">
            <x-button-spinner x-show="loading" />
            <span x-text="loading ? 'Memverifikasi…' : 'Verifikasi'">Verifikasi</span>
        </button>

        <p class="text-center text-sm text-ink-600">Tidak menerima kode? <a href="{{ route('password.request') }}" class="font-bold text-brand-700 hover:underline">Kirim ulang</a></p>
    </form>
</x-auth-shell>
