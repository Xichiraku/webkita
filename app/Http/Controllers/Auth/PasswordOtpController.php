<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Langkah 2 lupa password: verifikasi OTP, lalu terbitkan token reset (berlaku 15 menit).
 */
class PasswordOtpController extends Controller
{
    public const MAX_ATTEMPTS = 5;

    public const RESET_TOKEN_TTL_MINUTES = 15;

    public function create(Request $request): View
    {
        return view('auth.verify-otp', ['email' => $request->query('email')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ], [
            'email.required' => 'Email dan OTP tidak boleh kosong',
            'otp.required' => 'Email dan OTP tidak boleh kosong',
            'otp.digits' => 'OTP harus 6 digit angka',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            $this->fail('email', 'Email tidak ditemukan');
        }

        if (! $user->reset_otp) {
            $this->fail('otp', 'Kode OTP tidak ditemukan atau sudah dipakai. Minta kode baru.');
        }

        if ($user->reset_otp_attempts >= self::MAX_ATTEMPTS) {
            $this->fail('otp', 'Terlalu banyak percobaan salah. Minta kode OTP baru.');
        }

        if (! $user->reset_otp_expires_at || $user->reset_otp_expires_at->isPast()) {
            $this->fail('otp', 'OTP sudah kadaluarsa');
        }

        if (! hash_equals($user->reset_otp, PasswordResetLinkController::hashOtp($request->string('otp')->toString()))) {
            $attempts = $user->reset_otp_attempts + 1;

            // OTP dihanguskan setelah batas percobaan tercapai.
            $user->forceFill($attempts >= self::MAX_ATTEMPTS
                ? ['reset_otp' => null, 'reset_otp_expires_at' => null, 'reset_otp_attempts' => 0]
                : ['reset_otp_attempts' => $attempts])->save();

            $left = self::MAX_ATTEMPTS - $attempts;

            $this->fail('otp', $left > 0
                ? "OTP tidak valid. Sisa percobaan: {$left}"
                : 'Terlalu banyak percobaan salah. Minta kode OTP baru.');
        }

        $token = Str::random(64);

        $user->forceFill(['reset_otp' => null, 'reset_otp_expires_at' => null, 'reset_otp_attempts' => 0])->save();

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => hash('sha256', $token), 'created_at' => now()],
        );

        return redirect()->route('password.reset', ['token' => $token, 'email' => $user->email]);
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
