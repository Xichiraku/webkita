<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Langkah 3 lupa password: set password baru memakai token dari langkah OTP.
 */
class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', new StrongPassword],
        ], [
            'password.confirmed' => 'Konfirmasi password tidak sama',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->input('email'))->first();
        $user = User::where('email', $request->input('email'))->first();

        if (! $record || ! $user || ! hash_equals($record->token, hash('sha256', $request->string('token')->toString()))) {
            throw ValidationException::withMessages(['email' => 'Token tidak valid']);
        }

        if (Carbon::parse($record->created_at)->addMinutes(PasswordOtpController::RESET_TOKEN_TTL_MINUTES)->isPast()) {
            throw ValidationException::withMessages(['email' => 'Token sudah kadaluarsa']);
        }

        $user->forceFill([
            'password' => $request->input('password'),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        // Semua sesi login lama otomatis tidak berlaku setelah password diganti.
        DB::table('sessions')->where('user_id', $user->id)->delete();

        event(new PasswordReset($user));

        return redirect()->route('login')->with('status', 'Password berhasil diubah. Silakan login.');
    }
}
