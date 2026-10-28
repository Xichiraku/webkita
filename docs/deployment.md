# Deployment

Panduan umum untuk VPS Linux (Ubuntu) dengan Nginx + PHP-FPM + MySQL. Sesuaikan path dan domain.

## 1. Server

- PHP 8.3 (`fpm`, `mysql`, `mbstring`, `xml`, `curl`, `zip`), Composer 2, Node 20 (hanya untuk build), MySQL 8, Nginx, Supervisor.
- Kode di `/var/www/webkita`, dimiliki user deploy; `storage/` dan `bootstrap/cache/` harus bisa ditulis oleh user PHP-FPM.

## 2. Konfigurasi `.env` produksi

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://webkita.example.com
SESSION_SECURE_COOKIE=true

DB_CONNECTION=mysql
DB_DATABASE=webkita
DB_USERNAME=...        # user khusus, bukan root
DB_PASSWORD=...

QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb

# Reverb: buat kunci baru dengan `php artisan reverb:install`
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=webkita.example.com
REVERB_PORT=443
REVERB_SCHEME=https
# Server Reverb mendengarkan di port internal:
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

MIDTRANS_SERVER_KEY=...
MIDTRANS_CLIENT_KEY=...
MIDTRANS_IS_PRODUCTION=true

MAIL_MAILER=smtp       # atau resend; isi MAIL_* sesuai penyedia
MAIL_FROM_ADDRESS=...
```

Variabel `VITE_*` ditanam saat build, jadi isi dulu sebelum `npm run build`.

## 3. Rilis

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize          # cache config, route, view, event
php artisan queue:restart     # worker memuat kode baru
sudo supervisorctl restart webkita-reverb
```

Seed demo **jangan** dijalankan di produksi (akun demo berpassword publik). Buat admin pertama lewat tinker atau impor data lama ([migrasi-data.md](migrasi-data.md)).

## 4. Nginx

```nginx
server {
    server_name webkita.example.com;
    root /var/www/webkita/public;
    index index.php;

    client_max_body_size 12m;           # lampiran maks 10 MB

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\.(?!well-known) { deny all; }

    # WebSocket Reverb (path /app dan /apps)
    location ~ ^/(app|apps)/ {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_pass http://127.0.0.1:8080;
        proxy_read_timeout 120s;
    }

    listen 443 ssl;                      # sertifikat: certbot --nginx
}
```

Bila PHP berjalan di belakang proxy/load balancer, atur trusted proxies agar `https` terdeteksi.

## 5. Supervisor

`/etc/supervisor/conf.d/webkita.conf`:

```ini
[program:webkita-queue]
command=php /var/www/webkita/artisan queue:work --sleep=3 --tries=3 --max-time=3600
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600
numprocs=1

[program:webkita-reverb]
command=php /var/www/webkita/artisan reverb:start --host=127.0.0.1 --port=8080
user=www-data
autostart=true
autorestart=true
stopwaitsecs=30
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
```

Untuk banyak koneksi WebSocket naikkan `ulimit -n` (open files) proses Reverb.

## 6. Midtrans

- Payment Notification URL: `https://webkita.example.com/api/payments/notification`.
- Pastikan `MIDTRANS_IS_PRODUCTION=true` hanya bersama kunci produksi.

## 7. Cadangan & pemantauan

- Cadangkan database MySQL (`mysqldump`) dan folder `storage/app/private` (lampiran).
- Log aplikasi di `storage/logs`; `php artisan pail` untuk melihat langsung.
- Health check: `GET /up`.
