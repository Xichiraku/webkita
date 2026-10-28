# Fitur 5 — Chat Klien dan Developer

Prasyarat: [README.md](README.md) (K1–K4, K9, K12) dan [01-riwayat-aktivitas-dan-status.md](01-riwayat-aktivitas-dan-status.md). Dokumen PA 2.2.4 dan 2.2.7: chat adalah **komunikasi langsung** klien–developer; pesan disampaikan **real-time dengan Laravel Reverb**, sedangkan **penyimpanan tetap di basis data** (Reverb hanya menyampaikan).

## 1. Tujuan

Percakapan realtime satu-per-proyek antara klien dan developer yang ditugaskan untuk koordinasi cepat. Berbeda dengan komentar (menanggapi hasil secara formal): chat informal dan tidak mengubah status apa pun.

## 2. Cakupan

**Termasuk:** satu percakapan per proyek, kirim/terima realtime, lampiran gambar/PDF, penanda belum dibaca, hapus pesan sendiri, riwayat berpaginasi, indikator koneksi, pemulihan koneksi.

**Di luar cakupan:** "sedang mengetik", status online, edit pesan, balasan/kutipan, reaksi, mention, suara/video, grup, pencarian, email/push untuk pesan baru, peran admin terpisah (K4).

## 3. Aturan bisnis

| ID | Aturan |
|---|---|
| R5.1 | Percakapan = semua `chat_messages` dengan `project_id` yang sama; tidak ada tabel percakapan terpisah. |
| R5.2 | **Peserta chat:** klien pemilik dan developer yang **sedang** ditugaskan. Staf yang tidak ditugaskan (termasuk admin) dan pihak lain `403`. Developer yang dilepas kehilangan akses seketika (baca dan tulis). |
| R5.3 | Chat merupakan bagian Workspace sehingga **hanya tersedia setelah pembayaran terkonfirmasi** (K3): `409 "Workspace aktif setelah pembayaran terkonfirmasi."` Bila belum ada developer: `409 "Chat tersedia setelah developer ditugaskan."` |
| R5.4 | **Kirim pesan** hanya pada status proyek `approved` atau `process`. Pada `done` chat **hanya-baca** (`409 "Chat sudah ditutup. Anda masih dapat membaca riwayat."`). |
| R5.5 | Pesan: teks polos 1–2000 karakter setelah `trim`, **atau** minimal satu lampiran (pesan hanya-lampiran diizinkan). HTML tidak diizinkan; URL `http(s)` dijadikan tautan `rel="noopener noreferrer nofollow"` `target="_blank"`; skema lain tetap teks. |
| R5.6 | Lampiran per pesan: maks **3 berkas**, masing-masing **10 MB**, tipe `png`, `jpg/jpeg`, `pdf` (dari isi berkas). Disimpan di `attachments/{project_id}/chat/{message_id}/…` (disk privat); unduh hanya lewat endpoint terotorisasi. Gambar ditampilkan pratinjau kecil; PDF kartu unduh. |
| R5.7 | Pesan tidak dapat diedit (K11). **Hapus:** pengirim dalam **5 menit** (`403 "Waktu menghapus pesan sudah lewat."`). Soft delete: tampil "Pesan ini telah dihapus"; isi dan lampiran tidak lagi dikirim/diunduh. |
| R5.8 | Urutan pesan oleh `id` naik (bukan `created_at`). |
| R5.9 | **Belum dibaca:** per peserta disimpan `last_read_message_id`; belum dibaca = pesan **milik orang lain** dengan `id` lebih besar. Pesan sendiri tidak pernah dihitung. |
| R5.10 | Tanda terbaca dikirim ketika tab Chat terlihat **dan** jendela fokus, dengan id pesan terbaru yang tampil. `last_read_message_id` hanya boleh naik. |
| R5.11 | Hanya pesan **pertama** proyek yang dicatat ke log (`chat.started`). |
| R5.12 | Batas laju 30 pesan/menit/pengguna: `429 "Terlalu banyak pesan. Coba lagi sebentar lagi."` |
| R5.13 | Pesan disimpan selama proyek ada; tidak ada penghapusan otomatis. |

## 4. Model data

**`chat_messages`**: `id` (PK, acuan urutan); `project_id` (FK cascade); `user_id` (FK pengirim); `body` (text nullable); `created_at`; `deleted_at`, `deleted_by` (nullable). Indeks `(project_id, id)`.

**`chat_reads`**: `project_id` (FK cascade), `user_id` (FK), `last_read_message_id` (unsigned bigint, default 0), `updated_at`; kunci primer `(project_id, user_id)`.

Lampiran: `project_attachments` dengan `message_id` dan `uploaded_by`.

## 5. API

| Metode & path | Akses | Keterangan |
|---|---|---|
| `GET /api/projects/{project}/messages` | Peserta | `before_id`, `limit` (default 30, maks 50); terbaru dulu |
| `POST /api/projects/{project}/messages` | Peserta (R5.3–R5.4) | `multipart/form-data`: `body` (opsional bila ada `files`), `files[]` ≤ 3 |
| `DELETE /api/messages/{message}` | Pengirim ≤ 5 menit | Soft delete |
| `POST /api/projects/{project}/messages/read` | Peserta | `{ "last_message_id": 120 }` → `{ "unread": 0 }`; nilai lebih kecil diabaikan tanpa error |
| `GET /api/chat/unread` | Pengguna login | `{ "{project_id}": n }` hanya yang `n > 0` |
| `GET /api/attachments/download/{attachment}` | Peserta | endpoint yang ada; lampiran pesan terhapus → `404` |

Objek pesan:

```json
{ "id": 120, "body": "Halo, apakah logo sudah final?", "deleted": false,
  "author": { "id": 3, "name": "Budi", "role": "user" },
  "attachments": [ { "id": 40, "file_name": "logo.png", "file_size": 20480, "file_type": "image/png" } ],
  "can_delete": true, "created_at": "2026-10-28T14:15:00+07:00" }
```

Pesan terhapus: `body = null`, `attachments = []`, `deleted = true`. `GET .../messages` menyertakan `meta: { has_more, last_read_message_id }`.

Pesan error baku: `403 "Anda bukan peserta percakapan ini."` · `409` (R5.3, R5.4) · `422 "Tulis pesan atau lampirkan berkas."` · `422 "Maksimal 3 lampiran, masing-masing 10 MB (PNG, JPG, atau PDF)."` · `403` (R5.7) · `429` (R5.12).

## 6. Realtime

- Berlangganan `Echo.private('project.' + id)` (otorisasi README §9: klien pemilik atau developer ditugaskan).
- `ChatMessageSent` → `.message.created`, payload = objek pesan (tanpa `can_delete`; UI menghitung dari `author.id` dan waktu). Pengirim juga menerima event; UI **mencegah duplikasi** dengan menyatukan berdasarkan `id` (pesan dari respons `POST` ditambahkan optimistis lalu disatukan).
- `ChatMessageDeleted` → `.message.deleted` `{ id }`.
- Pesan disisipkan menurut `id`, bukan urutan kedatangan.
- **Pemulihan koneksi:** saat WebSocket tersambung kembali (atau tab fokus kembali setelah > 30 detik) UI memanggil `GET .../messages` untuk mengambil pesan dengan `id` > pesan terakhir yang dimiliki dan menyatukannya.
- `ChatUnreadChanged` → `.chat.unread` `{ project_id, unread }` pada channel `App.Models.User.{id}` agar daftar Orderan/"Proyek Saya" memperbarui lencana tanpa membuka channel tiap proyek.
- Event dikirim lewat antrian dan setelah commit; Reverb hanya penyampai, data tetap di MySQL.

## 7. UI

Tab **Chat** pada Workspace:

- Tinggi tetap; daftar pesan menggulir; kolom tulis di bawah.
- Gelembung: milik sendiri kanan (warna brand), lawan bicara kiri (abu), dengan nama, lencana peran ("Developer"/"Klien"), jam; pemisah tanggal ("Hari ini", "Kemarin", "28 Okt 2026").
- Muat awal 30 pesan terbaru, menggulir ke dasar; gulir ke atas memuat lebih lama (`before_id`) tanpa melompatkan posisi.
- Pesan baru saat pengguna di atas: tombol "↓ Pesan baru (n)"; di dasar: gulir otomatis.
- Pemisah "Pesan belum dibaca" pada posisi `last_read_message_id` saat tab dibuka.
- Kolom tulis: `Enter` kirim, `Shift+Enter` baris baru, tombol klip lampiran (pratinjau + hapus), penghitung setelah 1800 karakter, tombol Kirim nonaktif saat kosong atau mengirim.
- Status kirim: "Mengirim…"; gagal → ikon peringatan + "Kirim ulang" (pesan tidak hilang).
- Banner keadaan: "Chat tersedia setelah developer ditugaskan" (R5.3) dan "Chat sudah ditutup. Anda masih dapat membaca riwayat." (R5.4, kolom tulis disembunyikan).
- Indikator koneksi: titik hijau "Terhubung" / kuning "Menyambungkan…".
- Aksesibilitas: daftar `role="log"` + `aria-live="polite"`; fokus tetap di kolom tulis setelah kirim.
- Lencana belum dibaca pada tab Chat, kartu Orderan klien, dan baris "Proyek Saya" developer.

## 8. Kasus tepi

| Situasi | Perilaku |
|---|---|
| Developer diganti | Pesan lama tetap terbaca klien dan developer baru dengan nama pengirim asli; developer lama `403`; developer baru memulai dengan `last_read_message_id = 0`. |
| Proyek `done` saat mengetik | Kirim `409` (R5.4); teks tetap di kolom agar bisa disalin. |
| Pesan dihapus saat lawan membuka tab | Event `.message.deleted` mengganti isi dengan placeholder. |
| Dua tab pengguna sama | Keduanya menerima pesan; `read` dari satu tab menurunkan lencana di keduanya (`ChatUnreadChanged`). |
| Reverb mati | `POST` tetap berhasil dan tampil bagi pengirim; lawan melihatnya saat memuat ulang atau saat pemulihan koneksi; UI menampilkan "Menyambungkan…". |
| URL `javascript:` ditempel | Tetap teks biasa. |
| Pesan panjang tanpa spasi | Dipotong dengan `break-words`. |

## 9. Kriteria penerimaan

- [ ] Klien dan developer ditugaskan saling melihat pesan **tanpa refresh** (dua sesi berbeda).
- [ ] Staf yang tidak ditugaskan (termasuk admin), developer lain, dan developer yang dilepas `403` (endpoint dan channel); tamu `401`.
- [ ] Sebelum dibayar `409`; belum ada developer `409`; `done` hanya-baca; `approved`/`process` dapat kirim.
- [ ] Pesan 1–2000 karakter; kosong tanpa lampiran `422`; HTML tidak dieksekusi.
- [ ] Lampiran maks 3 × 10 MB, PNG/JPG/PDF dari isi berkas; pratinjau gambar; unduh terotorisasi.
- [ ] Hapus pesan hanya pengirim ≤ 5 menit; isi dan lampiran hilang dari API/UI/unduh.
- [ ] Belum dibaca per peserta, tidak menghitung pesan sendiri, `last_read_message_id` tidak turun, lencana tersinkron antar tab.
- [ ] Urutan benar walau event tidak berurutan; tanpa duplikasi antara respons `POST` dan event.
- [ ] Pemulihan koneksi mengambil pesan yang terlewat.
- [ ] Batas laju 30/menit; satu `chat.started` per proyek; pesan tersimpan di MySQL (bukan hanya disiarkan).

## 10. Rencana test

Feature: izin per peran dan status (R5.2–R5.4 lengkap, termasuk admin yang tidak ditugaskan), validasi pesan/lampiran, hapus (5 menit), `read` monoton, `unread` untuk dua peserta, batas laju, IDOR proyek A vs B, `Event::fake` (`ChatMessageSent`, `ChatMessageDeleted`, `ChatUnreadChanged`), otorisasi channel (klien/developer ditugaskan lolos; staf tidak ditugaskan, developer lama, pihak lain ditolak), sanitasi `body`. Browser: dua sesi saling berkirim; matikan lalu hidupkan Reverb dan pastikan pesan terlewat diambil.
