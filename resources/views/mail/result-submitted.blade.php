<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <h2>Halo, {{ $client->name }}!</h2>
    <p>Developer menyerahkan hasil untuk milestone <strong>{{ $milestone->title }}</strong> pada proyek <strong>{{ $project->project_title }}</strong>:</p>
    <p style="background: #F3F4F6; border-radius: 8px; padding: 12px 16px;"><strong>{{ $update->title }}</strong><br>{{ $update->note }}</p>
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ route('orderan.show', $project) }}#milestone" style="background-color: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-size: 16px;">Tinjau Hasil</a>
    </div>
    <hr style="border: none; border-top: 1px solid #E5E7EB; margin: 20px 0;" />
    <p style="color: #9CA3AF; font-size: 12px;">Email otomatis dari WebKita.</p>
</div>
