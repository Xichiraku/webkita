<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]{3,30}$/', 'unique:users,username'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', new StrongPassword],
            'subscribe_newsletter' => ['nullable', 'boolean'],
        ], [
            'username.regex' => 'Username 3-30 karakter: huruf, angka, titik, garis bawah, atau tanda hubung',
            'username.unique' => 'Username sudah digunakan',
            'email.unique' => 'Email sudah terdaftar',
            'password.confirmed' => 'Konfirmasi password tidak sama',
        ]);

        $user = new User([
            ...$data,
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'phone' => $data['phone'] ?? '',
            'subscribe_newsletter' => $request->boolean('subscribe_newsletter'),
        ]);
        $user->role = Role::User;
        $user->is_aktif = 'N';
        $user->save();

        try {
            Mail::to($user->email)->send(new VerifyEmailMail($user, $user->issueVerificationToken()));
        } catch (Throwable $e) {
            // Jangan sisakan akun tanpa verifikasi bila email gagal terkirim.
            report($e);
            $user->forceDelete();

            throw ValidationException::withMessages(['email' => 'Gagal mengirim email verifikasi']);
        }

        return redirect()->route('verification.notice', ['email' => $user->email]);
    }
}
