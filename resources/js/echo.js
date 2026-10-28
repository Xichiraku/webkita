import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Koneksi WebSocket (Laravel Reverb). Hanya dibuat untuk pengguna yang login,
 * karena semua channel yang dipakai bersifat privat. Bila server Reverb tidak
 * berjalan, halaman tetap berfungsi normal (hanya tanpa update langsung).
 */
const key = import.meta.env.VITE_REVERB_APP_KEY;
const loggedIn = !!document.querySelector('meta[name="user-id"]');

if (key && loggedIn) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}

/** ID user yang sedang login (atau null). */
window.userId = () => Number(document.querySelector('meta[name="user-id"]')?.content) || null;

/** Channel privat milik user yang login, atau null bila WebSocket tidak aktif. */
window.userChannel = () => (window.Echo && window.userId() ? window.Echo.private(`App.Models.User.${window.userId()}`) : null);
