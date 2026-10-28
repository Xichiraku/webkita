<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\StrongPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PasswordController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', new StrongPassword],
        ], [
            'current_password.current_password' => 'Password saat ini salah',
            'password.confirmed' => 'Konfirmasi password tidak sama',
        ]);

        $request->user()->update(['password' => $validated['password']]);

        // Sesi di perangkat lain berhenti berlaku setelah password diganti (sesi ini tetap).
        DB::table('sessions')->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();

        return back()->with('status', 'Password berhasil diubah.');
    }
}
