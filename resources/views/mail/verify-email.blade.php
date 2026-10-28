<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <h2>Halo, {{ $user->name }}!</h2>
    <p>Terima kasih telah mendaftar di <strong>WebKita</strong>.</p>
    <p>Klik tombol di bawah untuk memverifikasi email Anda:</p>
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $verifyUrl }}"
           style="background-color: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-size: 16px;">
            Verifikasi Email
        </a>
    </div>
    <p>Atau salin tautan berikut ke browser Anda:</p>
    <p style="color: #6B7280; word-break: break-all;">{{ $verifyUrl }}</p>
    <p>Tautan ini berlaku selama <strong>{{ \App\Models\User::VERIFICATION_TTL_HOURS }} jam</strong>.</p>
    <hr style="border: none; border-top: 1px solid #E5E7EB; margin: 20px 0;" />
    <p style="color: #9CA3AF; font-size: 12px;">Jika Anda tidak mendaftar di WebKita, abaikan email ini.</p>
</div>
