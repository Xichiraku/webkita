<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetOtpMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Langkah 1 lupa password: minta kode OTP 6 digit lewat email.
 */
class PasswordResetLinkController extends Controller
{
    public const OTP_TTL_MINUTES = 5;

    public static function hashOtp(string $otp): string
    {
        return hash_hmac('sha256', $otp, (string) config('app.key'));
    }

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']], [
            'email.required' => 'Email tidak boleh kosong',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => 'Email tidak ditemukan']);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'reset_otp' => self::hashOtp($otp),
            'reset_otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'reset_otp_attempts' => 0,
        ])->save();

        Mail::to($user->email)->send(new ResetOtpMail($user, $otp, self::OTP_TTL_MINUTES));

        return redirect()->route('password.otp', ['email' => $user->email])
            ->with('status', 'Kode OTP telah dikirim ke email Anda.');
    }
}
