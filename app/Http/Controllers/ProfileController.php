<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Profil dasar (padanan PUT /users/me). Email dan role tidak bisa diubah dari sini.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9._-]{3,30}$/', Rule::unique('users', 'username')->ignore($user->id)],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subscribe_newsletter' => ['nullable', 'boolean'],
        ], [
            'username.regex' => 'Username 3-30 karakter: huruf, angka, titik, garis bawah, atau tanda hubung',
            'username.unique' => 'Username sudah digunakan',
        ]);

        $user->update([
            ...$data,
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'phone' => $data['phone'] ?? '',
            'subscribe_newsletter' => $request->boolean('subscribe_newsletter'),
        ]);

        return redirect()->route('profile.edit')->with('status', 'Profil berhasil diperbarui.');
    }
}
