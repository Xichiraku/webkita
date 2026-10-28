<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /** Halaman "cek email Anda" + form kirim ulang. */
    public function notice(Request $request): View
    {
        return view('auth.verify-email', ['email' => $request->query('email')]);
    }

    /** Tautan dari email: /email/verify/{token}?email=... */
    public function verify(Request $request, string $token): RedirectResponse
    {
        $user = User::where('verification_token', hash('sha256', $token))->first();

        if (! $user) {
            $already = User::where('email', (string) $request->query('email'))->whereNotNull('email_verified_at')->exists();

            return $already
                ? redirect()->route('login')->with('status', 'Email sudah diverifikasi. Silakan login.')
                : redirect()->route('verification.notice')->withErrors(['email' => 'Token tidak valid']);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('login')->with('status', 'Email sudah diverifikasi. Silakan login.');
        }

        if (! $user->token_expires_at || $user->token_expires_at->isPast()) {
            return redirect()->route('verification.notice', ['email' => $user->email])
                ->withErrors(['email' => 'Token sudah kadaluarsa, silakan kirim ulang verifikasi']);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'is_aktif' => 'Y',
            'verification_token' => null,
            'token_expires_at' => null,
        ])->save();

        return redirect()->route('login')->with('status', 'Email berhasil diverifikasi! Akun Anda sudah aktif.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']], [
            'email.required' => 'Email tidak boleh kosong',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            throw ValidationException::withMessages(['email' => 'Email tidak ditemukan']);
        }

        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => 'Email sudah diverifikasi']);
        }

        Mail::to($user->email)->send(new VerifyEmailMail($user, $user->issueVerificationToken()));

        return back()->with('status', 'Email verifikasi telah dikirim ulang.');
    }
}
