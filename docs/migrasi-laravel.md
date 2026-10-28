# Rencana Migrasi WebKita → Laravel + MySQL + Reverb

> **Status: selesai (Fase 0-9).** Dokumen ini disimpan sebagai catatan keputusan. Instruksi pemakaian aplikasi ada di [README](../README.md). Aplikasi kini berada di root repo (bukan lagi subfolder `laravel/`); kode lama (Hono/React/Supabase) hanya ada di riwayat git.

Dokumen ini adalah instruksi bertahap untuk agent. Kerjakan **berurutan**, satu fase per commit/PR, dan jangan lanjut ke fase berikutnya sebelum "Definisi selesai" fase tersebut terpenuhi.

## Kondisi saat ini (sumber migrasi)

| Layer | Sekarang | Target |
|---|---|---|
| Backend | Hono + Cloudflare Workers (`backend/src`, ±2.400 baris TS) | Laravel 12 (PHP 8.3) |
| Database | Supabase PostgreSQL (`supabase/seed.sql`, `update_2026_10_28.sql`) | MySQL 8 |
| Frontend | React + Vite + Tailwind (`frontend/src`) + HTML statis di root | Blade + Tailwind + Vite di dalam Laravel |
| Auth | JWT buatan sendiri + OTP/verifikasi email via Resend | Laravel session auth (Breeze) + Mail |
| Pembayaran | Midtrans (webhook `/payment/notification`) | Midtrans (SDK PHP), webhook Laravel |
| Realtime | Tidak ada | Laravel Reverb + Echo |
| Dev env | Docker Compose, Wrangler | Laragon (PHP 8.3, Composer 2.10, MySQL 8.4 sudah ada) |

Tabel yang harus dimigrasi: `users`, `projects`, `payments`, `project_attachments`, `pricing_plans`, `plan_features`, `plan_not_included`.

Endpoint yang harus dipertahankan perilakunya: `auth` (login email/username, register, verify-email, forgot-password, verify-otp, reset-password, resend-verification, logout, profile), `users` (PUT /me, list, delete), `projects` (CRUD, status, price, my, by user), `pricing` (public + admin CRUD + toggle-active), `attachments` (upload, list, download), `payments` (create, status, all, by project, invoice, notification webhook).

Halaman frontend yang harus dipertahankan: Home, Harga, CaraKerja, InformasiDetail, Orderan, InvoicePage, Profil, Login, Register, VerifyEmail, VerifyOTP, ForgotPassword, ResetPassword, Admin (Dashboard, Orders, Transaksi, Paket/Pricing, Pengguna).

## Keputusan default (ubah di awal bila tidak sesuai)

- Laravel dipasang di **subfolder `laravel/`** dulu pada branch `migrasi-laravel`; kode lama tetap utuh sebagai referensi. Di Fase 9 isinya dipindah ke root dan kode lama dihapus.
- Frontend: **Blade + Tailwind + Alpine.js** (server-rendered). Bukan Inertia/React, karena permintaan adalah "semua pakai Laravel". Komponen UI React (`Modal`, `Toast`, `StatusChip`, dst.) dipetakan ke Blade component.
- Auth: **Laravel Breeze (Blade)**, bukan JWT. Role (`admin`, `developer`, `user`) via kolom `role` + Gate/middleware.
- Primary key: tetap **UUID** (`HasUuids`) agar data lama bisa diimpor tanpa remap.
- Storage lampiran: disk `local`/`public` Laravel (menggantikan Supabase Storage).
- Antrian (queue) memakai driver `database`.

---

## Fase 0 — Persiapan lingkungan

1. `git checkout -b migrasi-laravel`.
2. Pastikan MySQL Laragon berjalan (binary ada di `C:\laragon\bin\mysql\mysql-8.4.3-winx64`, belum ada di PATH). Buat database `webkita` dengan `utf8mb4_unicode_ci`.
3. Verifikasi: `php -v` (8.3), `composer --version`, ekstensi PHP `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `sockets`/`pcntl` tidak wajib di Windows untuk Reverb.
4. Baca `backend/src/**` dan `frontend/src/**` penuh, buat inventaris singkat (endpoint, validasi, aturan bisnis, format response) di `docs/inventaris.md`. Ini jadi acuan paritas fitur.

**Selesai bila:** branch ada, DB kosong `webkita` bisa diakses, inventaris tertulis.

## Fase 1 — Install Laravel

```bash
composer create-project laravel/laravel laravel
cd laravel
php artisan --version
```

1. Konfigurasi `.env`: `APP_NAME=WebKita`, `APP_LOCALE=id`, `APP_TIMEZONE=Asia/Jakarta`, `DB_CONNECTION=mysql`, `DB_DATABASE=webkita`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`.
2. `php artisan key:generate`, `php artisan migrate` (tabel bawaan) — pastikan koneksi MySQL berhasil.
3. `npm install` lalu `npm run build` sukses.
4. Tambahkan `.env.example` yang rapi; pastikan `.env` ter-ignore.
5. Commit: "chore: install Laravel 12".

**Selesai bila:** `php artisan serve` menampilkan halaman welcome dan `migrate:status` hijau di MySQL.

## Fase 2 — Skema database MySQL

Terjemahkan `supabase/seed.sql` + `update_2026_10_28.sql` menjadi migration Laravel (satu migration per tabel, urut sesuai FK):

1. Perubahan tipe PostgreSQL → MySQL: `uuid` → `uuid()`, `timestamptz` → `timestamp`, `jsonb` → `json`, `boolean`, enum/check → `enum` atau `string` + cast PHP Enum, `now()` default → `useCurrent()`.
2. Modifikasi tabel `users` bawaan Laravel agar memuat: `username` (unique), `first_name`, `last_name`, `phone`, `group_id`, `role`, `is_aktif`, `subscribe_newsletter`, `email_verified_at`, timestamps. Hapus kolom `name` bawaan.
3. Buat tabel: `projects`, `payments`, `project_attachments`, `pricing_plans`, `plan_features`, `plan_not_included`, plus tabel OTP reset password (atau pakai `password_reset_tokens` + kolom `otp`).
4. Buat **Eloquent Model**, relasi, `$casts`, dan **Enum** untuk status project/payment/role.
5. Buat **Factory** dan **Seeder** dari data demo di `seed.sql` (admin, user contoh, paket harga + fitur).
6. Tulis Pest/PHPUnit test sederhana untuk relasi dan seeder.

**Selesai bila:** `php artisan migrate:fresh --seed` berhasil dan data demo setara `seed.sql`.

## Fase 3 — Autentikasi & otorisasi

1. `composer require laravel/breeze --dev` → `php artisan breeze:install blade` (menghasilkan Tailwind + Alpine + Vite).
2. Sesuaikan: login via **email atau username**, register dengan field `username/first_name/last_name/phone/subscribe_newsletter`, verifikasi email (`MustVerifyEmail`).
3. Alur lupa password dengan **OTP**: `ForgotPassword` → kirim OTP (Mail/Notification, queued) → `VerifyOTP` → `ResetPassword`. Pertahankan masa berlaku & batas percobaan dari `auth.service.ts`.
4. Resend verifikasi dengan throttle.
5. Middleware `role:admin`, `role:admin,developer` (padanan `roleMiddleware`) + Policy untuk Project/Payment/Attachment (pemilik atau admin).
6. Mail: konfigurasi SMTP (Mailpit/Laragon untuk lokal; Resend lewat `resend/resend-laravel` bila tetap dipakai produksi). Port template dari `web/html/email_template.html` ke Markdown/Blade mail.
7. Feature test: register, login email/username, verifikasi, OTP, logout, akses terlarang berdasarkan role.

**Selesai bila:** seluruh alur auth lolos test dan bisa dijalankan manual di browser.

## Fase 4 — Logika domain (service, controller, request)

Port per modul, **satu modul satu commit**, urutan: Pricing → Project → Attachment → User/Profile → Payment.

Untuk tiap modul:
1. `FormRequest` untuk validasi (padanan `utils/validation.ts`), pesan error bahasa Indonesia.
2. `Service`/`Action` class memegang logika dari `backend/src/services/*.service.ts`; controller tipis.
3. Resource/route web (Blade) untuk halaman, dan `routes/api.php` + `JsonResource` bila ada bagian yang tetap butuh JSON (fetch dari Alpine, webhook).
4. Attachment: `Storage` disk, validasi tipe/ukuran, download via controller terotorisasi (bukan URL publik langsung).
5. Payment: `composer require midtrans/midtrans-php`; `create` (Snap token), `status`, `invoice`, dan webhook `POST /payment/notification` (di-exclude dari CSRF, **verifikasi signature key** Midtrans, idempotent). Config di `config/services.php`.
6. Feature test per modul, termasuk kasus otorisasi dan webhook (payload Midtrans palsu).

**Selesai bila:** semua endpoint di inventaris punya padanan Laravel dan test-nya hijau.

## Fase 5 — Frontend Blade

1. Pindahkan `tailwind.config.js` & `index.css` (token warna `ink-*`, dll.) ke setup Tailwind Laravel (`resources/css/app.css`, `vite.config.js`). Aset ke `resources/images` / `public`.
2. Buat `layouts/app.blade.php` (padanan `Layout.jsx` + `navbar.jsx` + `Footer.jsx`), `layouts/admin.blade.php` (padanan `AdminSidebar`), dan `layouts/guest.blade.php` (padanan `AuthShell`).
3. Buat Blade component: `modal`, `toast`, `spinner`, `status-chip`, `field-error`, `password-input`, `pager`, `logo`. Interaktivitas (modal, toast, CountUp, `useReveal`) dengan Alpine.js.
4. Port halaman publik: Home, Harga (dari DB), CaraKerja, InformasiDetail.
5. Port halaman user: Orderan (form + daftar project + upload lampiran), InvoicePage, Profil.
6. Port halaman auth (override view Breeze dengan desain WebKita).
7. Port Admin: Dashboard + StatsGrid, Orders (tabel, detail modal, StatusUpdater, pagination), Transaksi, Pricing CRUD, Pengguna.
8. Hapus ketergantungan pada `api_client.js` / `provider/*`: data sekarang dari controller/view, mutasi lewat form POST atau fetch ke route Laravel.
9. Pastikan responsif & aksesibilitas setara versi React (bandingkan screenshot lama vs baru).

**Selesai bila:** semua halaman di daftar tampil dan berfungsi end-to-end tanpa React/Vite lama.

## Fase 6 — Laravel Reverb (realtime)

```bash
php artisan install:broadcasting   # pilih Reverb, pasang laravel-echo + pusher-js
```

1. Isi `.env`: `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID/KEY/SECRET`, `REVERB_HOST=localhost`, `REVERB_PORT=8080`, `REVERB_SCHEME=http`, dan `VITE_REVERB_*`.
2. Event (implement `ShouldBroadcast`, queued):
   - `ProjectStatusUpdated` → channel privat `App.Models.User.{id}` (klien melihat status order berubah live).
   - `PaymentStatusChanged` → dikirim dari webhook Midtrans ke pemilik project (halaman invoice/orderan update tanpa refresh).
   - `NewProjectCreated` → channel privat `admin.orders` (dashboard admin & tabel order bertambah live).
3. `routes/channels.php`: otorisasi channel (pemilik user / role admin).
4. Frontend: `resources/js/echo.js`, listener di halaman Orderan, Invoice, dan Admin Orders/Dashboard; tampilkan Toast saat event masuk dan perbarui baris/StatusChip.
5. Jalankan: `php artisan reverb:start --debug` + `php artisan queue:work` + `npm run dev`. Tambahkan script `composer run dev` (concurrently) agar semua proses jalan sekali perintah.
6. Test: `Event::fake()` memastikan event ter-dispatch di tiap perubahan status; uji otorisasi channel.

**Selesai bila:** mengubah status order di admin memunculkan update langsung di tab klien tanpa refresh.

## Fase 7 — Migrasi data (opsional, bila ada data produksi)

1. Ekspor tabel dari Supabase (CSV/`pg_dump --data-only`).
2. Buat Artisan command `webkita:import-legacy` yang membaca CSV dan menulis ke MySQL (UUID dipertahankan; password hash lama perlu dicek kompatibilitasnya dengan bcrypt, bila tidak → paksa reset password).
3. Pindahkan file lampiran dari Supabase Storage ke disk Laravel dan perbarui path.
4. Validasi jumlah baris & checksum per tabel.

**Selesai bila:** hitungan data sumber dan target cocok.

## Fase 8 — Kualitas & keamanan

1. `composer require laravel/pint --dev` → format kode; `larastan` level moderat.
2. Rate limit login/OTP/webhook, CSRF aktif (kecuali webhook), Policy di semua aksi data, `$fillable` eksplisit, cegah mass assignment `role`/`is_aktif`.
3. Header keamanan & validasi upload; jangan commit `.env`, kunci Midtrans, atau `log/error.log`.
4. Jalankan seluruh test (`php artisan test`) dan uji manual alur utama: daftar → verifikasi → buat order → bayar (sandbox Midtrans) → admin ubah status → klien lihat realtime.

## Fase 9 — Cutover & pembersihan

1. Pindahkan isi `laravel/` ke root repo; hapus `backend/`, `frontend/`, HTML statis root, `supabase/`, `go.mod`, `go.sum`, `requirement.txt`, `vercel.json`, `package.json` lama, `docker-compose.yml` lama (ganti dengan Sail atau cukup Laragon — putuskan bersama user).
2. Tulis ulang `README.md` dan `SETUP.md`: prasyarat (PHP 8.3, Composer, MySQL, Node), langkah `composer install`, `.env`, `migrate --seed`, `composer run dev`, serta konfigurasi Reverb & Midtrans.
3. Deployment note: proses `reverb:start` & `queue:work` via Supervisor/systemd, reverse proxy WebSocket (Nginx), `php artisan optimize`.
4. Buka PR `migrasi-laravel` → `main` dengan ringkasan paritas fitur dan hasil test.

**Selesai bila:** clone bersih + ikuti README menghasilkan aplikasi berjalan lengkap dengan realtime.

---

## Aturan kerja untuk agent

- Satu fase = satu atau beberapa commit kecil dengan pesan jelas; jangan menggabungkan fase.
- Setiap fase ditutup dengan menjalankan test yang relevan dan melaporkan hasilnya apa adanya (termasuk yang gagal/dilewati).
- Jangan menghapus kode lama sebelum Fase 9.
- Tanyakan ke user bila menemukan aturan bisnis yang ambigu di kode lama, alih-alih menebak.
- Jangan pernah memasukkan kunci rahasia asli ke repo; pakai `.env` dan `.env.example`.
