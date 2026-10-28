# Migrasi data dari Supabase ke MySQL

Gunakan hanya bila ada data produksi di Supabase yang harus dibawa. Untuk instalasi baru, cukup `php artisan migrate --seed`.

## 1. Ekspor dari Supabase (PostgreSQL)

Ekspor tujuh tabel ke CSV **dengan baris header**. Dengan `psql` (ganti `$DATABASE_URL` dengan connection string Supabase):

```bash
mkdir export && cd export
for t in users pricing_plans plan_features plan_not_included projects payments project_attachments; do
  psql "$DATABASE_URL" -c "\copy (select * from public.$t order by 1) to '$t.csv' csv header"
done
```

Alternatif: tombol **Export to CSV** di Table Editor Supabase untuk tiap tabel (simpan dengan nama `<tabel>.csv`).

Kolom di luar skema baru (mis. `token_version`) otomatis diabaikan. Tabel yang kosong boleh tidak diekspor (kecuali `users.csv` dan `projects.csv` yang wajib ada).

## 2. Ekspor berkas lampiran (opsional)

Unduh isi bucket **project-files** ke satu folder dengan struktur yang sama dengan kolom `storage_path`
(mis. `files/attachments/12/1730000000000.pdf`). Bisa lewat dashboard Supabase Storage, Supabase CLI, atau S3-compatible endpoint Supabase.

## 3. Impor

Database MySQL harus sudah dimigrasi (`php artisan migrate`) dan **kosong**.

```bash
php artisan webkita:import-legacy export --files=files --dry-run   # cek dulu, tidak menyimpan apa pun
php artisan webkita:import-legacy export --files=files             # impor sungguhan
```

Opsi:

| Opsi | Fungsi |
|---|---|
| `--dry-run` | Validasi + simulasi penuh di dalam transaksi, lalu dibatalkan |
| `--files=<folder>` | Salin lampiran ke disk privat aplikasi (`storage/app/private`) |
| `--skip-invalid` | Lewati baris bermasalah dan impor sisanya (default: batalkan semuanya) |
| `--update` | Izinkan menimpa baris ber-ID sama pada tabel yang sudah berisi |

Yang dilakukan perintah ini:

- **ID dipertahankan**, jadi relasi dan nomor invoice (`INV-0012-2026`) tidak berubah.
- **Password tetap berlaku.** Hash bcrypt `$2b$` dari Node diubah ke `$2y$` (algoritma sama, hanya prefiks). Tanpa ini Laravel menolak login semua pengguna lama.
- Waktu `timestamptz` dikonversi ke zona waktu aplikasi (`Asia/Jakarta`).
- Pembayaran lunas tanpa `paid_at` diisi dari `updated_at` (sama dengan `update_2026_10_28.sql`).
- Token verifikasi email dan OTP lama **dibuang** (formatnya beda dan umurnya pendek).
- Semua ditulis dalam **satu transaksi**; setelah itu jumlah baris, total nominal pembayaran, dan jumlah transaksi lunas dicocokkan dengan sumber. Bila tidak cocok, semuanya dibatalkan.
- Validasi sebelum menulis: relasi (FK), status project/pembayaran, role, duplikat `username`/`email`/`order_id`, dan path lampiran yang mencoba keluar folder (`..`).

## 4. Sesudah impor

- Pengguna yang **belum sempat verifikasi email** harus memakai "Kirim Ulang Verifikasi" (token lama dibuang).
- Pengguna yang sedang di tengah reset password harus meminta OTP baru.
- Semua pengguna perlu login ulang (sesi lama tidak ikut).
- Atur ulang webhook Midtrans ke `https://<domain-baru>/api/payments/notification`.
- Periksa peringatan lampiran yang berkasnya tidak ditemukan; berkas itu perlu diunduh ulang dari Supabase.

## Cutover yang disarankan

1. Hentikan penulisan di aplikasi lama (mode baca-saja atau jendela pemeliharaan singkat).
2. Ekspor data (langkah 1-2), impor ke MySQL, jalankan `--dry-run` dulu.
3. Arahkan domain dan webhook Midtrans ke aplikasi Laravel.
4. Simpan ekspor CSV sebagai cadangan sampai aplikasi baru terbukti stabil.
