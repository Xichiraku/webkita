<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
    <h2>Halo, {{ $recipient->name }}!</h2>
    <p>Semua milestone proyek <strong>{{ $project->project_title }}</strong> telah disetujui. Proyek dinyatakan <strong>selesai</strong>.</p>
    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ $url }}" style="background-color: #4F46E5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-size: 16px;">Buka Workspace</a>
    </div>
    <hr style="border: none; border-top: 1px solid #E5E7EB; margin: 20px 0;" />
    <p style="color: #9CA3AF; font-size: 12px;">Email otomatis dari WebKita.</p>
</div>
