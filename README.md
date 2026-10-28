# WebKita

Platform marketplace yang menghubungkan pemilik bisnis dengan developer profesional untuk pengerjaan proyek website. Klien memilih paket, mengajukan proyek, memantau statusnya, dan membayar lewat Midtrans; admin mengelola orderan, harga, pengguna, dan transaksi.

> Aplikasi ini dimigrasi dari Hono + Cloudflare Workers + Supabase + React ke satu aplikasi Laravel. Catatan migrasi ada di [docs/migrasi-laravel.md](docs/migrasi-laravel.md); kode lama ada di riwayat git (branch `main` sebelum migrasi).

## Tech stack

| Layer | Teknologi |
|---|---|
| Backend & frontend | Laravel 13 (PHP 8.3), Blade, Alpine.js |
| Database | MySQL 8 |
| Realtime | Laravel Reverb (WebSocket) + Laravel Echo |
| Styling | Tailwind CSS 3, ikon Lucide, build dengan Vite |
| Pembayaran | Midtrans Snap |
| Auth | Session Laravel (login email/username, verifikasi email, reset password dengan OTP) |
| Kualitas | Pest/PHPUnit, Laravel Pint, Larastan |

## Fitur

- **Publik:** beranda, cara kerja, paket harga dengan kalkulator budget.
- **Klien:** daftar/login, form pengajuan proyek 3 langkah (dengan lampiran), daftar orderan dengan status live, pembayaran Midtrans, invoice cetak, profil.
- **Admin & developer:** dashboard, manajemen orderan (setujui, tolak dengan alasan, tetapkan harga sekali, status satu arah), pengguna, transaksi, paket layanan.
- **Realtime:** status orderan, harga, dan pembayaran ter-update tanpa refresh di sisi klien dan admin.
- **Project Workspace** (aktif setelah proyek dibayar; peserta: klien pemilik dan developer yang ditugaskan): milestone, progres dan hasil pengerjaan, komentar, permintaan revisi dengan kuota, persetujuan per milestone (proyek selesai otomatis bila semua disetujui), chat klien-developer realtime, riwayat aktivitas, dan status proyek. Spesifikasi lengkap di [docs/fitur](docs/fitur/README.md).

## Prasyarat

- PHP 8.3+ dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`
- Composer 2
- Node.js 20+ dan npm
- MySQL 8 (di Windows bisa lewat [Laragon](https://laragon.org))

## Menjalankan secara lokal

```bash
git clone <url-repo> webkita && cd webkita
composer run setup          # composer install, .env, key:generate, migrate, npm install, build
```

Buat database kosong (sekali saja) dan sesuaikan `DB_*` di `.env`:

```sql
CREATE DATABASE webkita CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
php artisan migrate --seed  # tabel + data demo
composer run dev            # server, queue, Vite, dan Reverb sekaligus
```

Buka <http://localhost:8000>. Akun demo (dari seeder):

| Peran | Email / username | Password |
|---|---|---|
| User | `demo@webkita.local` / `demo` | `Demo1234!` |
| Admin | `admin@webkita.local` / `admin-demo` | `Admin1234!` |

Ganti password akun demo sebelum dipakai di luar mesin lokal.

### Mencoba Project Workspace

Seeder membuat tiga proyek siap pakai untuk `demo` (klien) dan `admin-demo` (developer; admin = developer):

| Proyek | Keadaan |
|---|---|
| Website katalog produk demo | Berbayar, milestone baru direncanakan: coba mengirim progres dan hasil pertama |
| Aplikasi toko online demo | Berjalan: milestone disetujui, menunggu review (setelah satu revisi), dikerjakan, dan direncanakan; ada komentar dan chat |
| Landing page selesai demo | Selesai: semua milestone disetujui, chat hanya-baca |

Login `demo` untuk membuka dari menu **Orderan**, dan `admin-demo` dari menu **Proyek Saya**. Untuk melihat dua sisi sekaligus, buka satu peran di jendela biasa dan satu lagi di jendela penyamaran.

> **Realtime butuh dua proses**: `reverb:start` (WebSocket) dan `queue:work` (event dikirim lewat queue). `composer run dev` sudah menjalankan keduanya. Tanpa keduanya aplikasi tetap berfungsi, hanya tanpa pembaruan otomatis.

### Email

Secara default `MAIL_MAILER=log`: email verifikasi dan OTP ditulis ke `storage/logs/laravel.log`. Untuk mencoba di inbox, arahkan `MAIL_*` ke Mailpit/Mailtrap atau SMTP lain.

### Pembayaran (Midtrans sandbox)

Isi di `.env` lalu jalankan ulang `npm run build` bila mengubah `.env` Vite:

```
MIDTRANS_SERVER_KEY=SB-Mid-server-...
MIDTRANS_CLIENT_KEY=SB-Mid-client-...
MIDTRANS_IS_PRODUCTION=false
```

Atur **Payment Notification URL** di dashboard Midtrans ke `https://<domain>/api/payments/notification`. Webhook memverifikasi signature dan memeriksa nominal; untuk uji lokal gunakan tunnel (mis. ngrok/Cloudflare Tunnel).

## Perintah berguna

| Perintah | Fungsi |
|---|---|
| `php artisan test` | Jalankan seluruh test (butuh `npm run build` sekali, karena view memanggil Vite) |
| `composer run lint` | Cek gaya kode (Pint) |
| `composer run analyse` | Analisis statis (Larastan level 5) |
| `composer run check` | Lint + analisis + test |
| `php artisan db:seed --class=WorkspaceDemoSeeder` | Tambah data uji Workspace (proyek yang sudah ada dilewati) |
| `php artisan webkita:import-legacy <folder>` | Impor data lama dari ekspor CSV Supabase ([panduan](docs/migrasi-data.md)) |

## Struktur proyek

```
app/
  Enums/            Role, ProjectStatus (alur status), PaymentStatus, MilestoneStatus, ReviewStatus, RevisionStatus
  Events/           ProjectUpdated, NewProjectCreated, PaymentStatusChanged, Milestone*, Chat*, UpdateReviewed (broadcast)
  Http/Controllers/ Controller halaman (PageController), API JSON, dan Auth/
  Models/           User, Project, Payment, ProjectAttachment, PricingPlan, ...
  Policies/         Akses project & pembayaran (pemilik atau staf)
  Services/         Logika bisnis: Project, Payment (Midtrans), Pricing, Attachment, LegacyImporter,
                    Milestone, MilestoneUpdate, Review, Comment, Chat, ProjectActivityLogger
resources/
  views/            Blade: pages/, auth/, admin/, profile/, components/
  js/               app.js (Alpine, helper API), admin.js, echo.js (Reverb)
routes/             web.php (halaman + /api/*), auth.php, channels.php
database/           migrations, factories, seeders
tests/Feature/      Auth, API per modul, realtime, keamanan, alur lengkap
docs/               Rencana migrasi, panduan migrasi data, deployment
```

## API JSON

Dipanggil dari halaman (sesi login + CSRF), semua di bawah `/api`:

| Area | Endpoint |
|---|---|
| Harga | `GET /pricing/public`, `GET /pricing/public/tier/{tier}`; admin/developer: CRUD `/pricing`, `PATCH /pricing/{id}/toggle-active` |
| Orderan | `POST /projects`, `GET /projects/my`, `GET /projects/{id}`; admin: `GET /projects`, `PATCH /projects/{id}/status`, `PATCH /projects/{id}/price`, `DELETE /projects/{id}` |
| Lampiran | `POST/GET /attachments/{project}`, `GET /attachments/download/{id}` |
| Pembayaran | `POST /payments/create/{project}`, `GET /payments/status/{orderId}`, `GET /payments/project/{project}`, `GET /payments/invoice/{project}`, admin: `GET /payments`; webhook publik: `POST /payments/notification` |
| Pengguna | admin/developer: `GET /users`; admin: `DELETE /users/{username}` |
| Workspace | `GET/POST /projects/{id}/milestones`, `PATCH /projects/{id}/milestones/order`, `PATCH/DELETE /milestones/{id}`, `GET/POST /milestones/{id}/updates`, `GET /projects/{id}/activities`, `GET/POST /projects/{id}/comments`, `DELETE /comments/{id}`, `POST /updates/{id}/approve`, `POST /updates/{id}/request-revision`, `GET /projects/{id}/revisions`, `PATCH /projects/{id}/revision-limit`, `GET/POST /projects/{id}/messages`, `POST /projects/{id}/messages/read`, `DELETE /messages/{id}`, `GET /chat/unread`; developer: `PATCH /projects/{id}/developer` (admin menugaskan) |

## Keamanan

CSRF aktif (kecuali webhook Midtrans yang diamankan signature), policy di setiap akses data, mass-assignment dibatasi (`role`, `is_aktif`, `status` tidak bisa diisi dari request), rate limit login/OTP/API/unggah, header keamanan, lampiran disimpan di disk privat dan hanya bisa diunduh lewat controller terotorisasi. Ada sapuan IDOR otomatis di `tests/Feature/SecurityTest.php`.

## Deployment

Lihat [docs/deployment.md](docs/deployment.md) (Nginx, Supervisor untuk queue dan Reverb, variabel produksi).
