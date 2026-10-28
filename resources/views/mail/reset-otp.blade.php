<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <h2>Halo, {{ $user->name }}!</h2>
    <p>Kami menerima permintaan reset password untuk akun Anda.</p>
    <p>Gunakan kode OTP berikut untuk melanjutkan:</p>
    <div style="text-align: center; margin: 30px 0;">
        <div style="background-color: #F3F4F6; border-radius: 8px; padding: 20px; display: inline-block;">
            <span style="font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #4F46E5;">{{ $otp }}</span>
        </div>
    </div>
    <p>Kode ini berlaku selama <strong>{{ $ttlMinutes }} menit</strong>.</p>
    <hr style="border: none; border-top: 1px solid #E5E7EB; margin: 20px 0;" />
    <p style="color: #9CA3AF; font-size: 12px;">Jika Anda tidak meminta reset password, abaikan email ini.</p>
</div>
