<?php

namespace App\Providers;

use App\Policies\ProjectWorkspacePolicy;
use App\Services\Payment\MidtransSnapGateway;
use App\Services\Payment\SnapGateway;
use App\Support\Ui;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SnapGateway::class, MidtransSnapGateway::class);
    }

    public function boot(): void
    {
        // Agar view cukup menulis Ui::BTN_PRIMARY, Ui::rp($n), dst.
        if (! class_exists('Ui')) {
            class_alias(Ui::class, 'Ui');
        }

        Gate::define('workspace.participate', [ProjectWorkspacePolicy::class, 'participate']);
        Gate::define('workspace.access', [ProjectWorkspacePolicy::class, 'access']);

        // Di produksi di belakang HTTPS, semua URL yang dibuat (tautan email, asset) harus https.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // API JSON milik halaman web: per pengguna (atau per IP bila belum login).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        // Update progres/hasil developer: 10 per menit per pengguna (K12).
        RateLimiter::for('updates', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        // Komentar: 20 per menit per pengguna (K12).
        RateLimiter::for('comments', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));

        // Pesan chat: 30 per menit per pengguna (K12, R5.12).
        RateLimiter::for('chat', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip())
            ->response(fn () => response()->json(['status' => 'error', 'message' => 'Terlalu banyak pesan. Coba lagi sebentar lagi.'], 429)));

        // Unggah lampiran lebih ketat: berat di disk dan bandwidth.
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
    }
}
