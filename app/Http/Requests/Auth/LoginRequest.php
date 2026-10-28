<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Email atau username wajib diisi',
            'password.required' => 'Password wajib diisi',
        ];
    }

    /**
     * Login dengan email ATAU username. Urutan pengecekan sama dengan backend lama:
     * password -> email terverifikasi -> akun aktif.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = trim((string) $this->input('login'));
        $field = str_contains($login, '@') ? 'email' : 'username';
        $user = User::where($field, $login)->first();

        // Hash::check tetap dijalankan walau user tidak ada, agar waktu respons tidak membocorkan
        // apakah email/username terdaftar.
        $hash = $user->password ?? '$2y$12$'.str_repeat('.', 53);
        $valid = Hash::check((string) $this->input('password'), $hash);

        if (! $user || ! $valid) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['login' => 'Email/username atau password salah']);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['login' => 'Email belum diverifikasi, silakan cek inbox Anda']);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['login' => 'Akun tidak aktif']);
        }

        RateLimiter::clear($this->throttleKey());

        Auth::login($user, $this->boolean('remember'));
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('login')).'|'.$this->ip());
    }
}
