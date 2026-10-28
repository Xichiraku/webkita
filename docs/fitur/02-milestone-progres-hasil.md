# Fitur 1 dan 2 — Milestone serta Submit Progres dan Hasil Pengerjaan

Prasyarat: [README.md](README.md) (K1–K13, status proyek dan milestone, penugasan, Workspace) dan [01-riwayat-aktivitas-dan-status.md](01-riwayat-aktivitas-dan-status.md). Dokumen PA 2.2.3: *milestone adalah unit pelaporan progres dan hasil pengerjaan*; klien melihat perkembangan **per tahapan**.

## 1. Tujuan

- **Milestone:** developer memecah pekerjaan menjadi tahapan bernomor; klien melihat daftar tahapan dan statusnya.
- **Submit progres dan hasil:** pada tiap milestone developer mengirim **progres** (laporan perkembangan) dan **hasil** (penyerahan untuk direview klien) beserta catatan, tautan, dan berkas.
- Persentase proyek diturunkan dari persentase milestone.

## 2. Cakupan

**Termasuk:** CRUD milestone oleh developer (dengan batasan), urutan, tenggat opsional, update progres dan hasil, unggah berkas dan tautan, persentase, perubahan status otomatis, daftar, unduh, realtime, email hasil.

**Di luar cakupan:** bobot milestone berbeda, ketergantungan antar milestone, penagihan per milestone (urusan modul pembayaran), persetujuan klien atas rencana milestone, pratinjau berkas dalam halaman, edit/hapus update setelah dikirim.

## 3. Aturan bisnis — Milestone

| ID | Aturan |
|---|---|
| R1.1 | Hanya **developer yang ditugaskan** boleh membuat, mengubah, mengurutkan, atau menghapus milestone. Klien hanya membaca (`403` selain itu). |
| R1.2 | Milestone hanya boleh dikelola saat Workspace aktif (pembayaran terkonfirmasi) dan status proyek `approved` atau `process`. Pada `done` semua milestone terkunci. |
| R1.3 | Jumlah milestone per proyek: **minimal 1** (syarat mengirim update, R2.1) dan **maksimal 10**. Melebihi: `422 "Maksimal 10 milestone per proyek."` |
| R1.4 | Field: `title` 3–100 karakter (wajib), `description` 0–1000, `due_date` tanggal opsional (tidak boleh di masa lalu saat dibuat), `position` ditentukan sistem (1..n tanpa celah). |
| R1.5 | **Edit:** selama milestone `planned`, semua field boleh diubah. Setelah punya update (status bukan `planned`): `title` dan `description` **terkunci**; hanya `due_date` yang boleh diubah. |
| R1.6 | **Hapus:** hanya milestone `planned` tanpa update. Selain itu `409 "Milestone yang sudah dikerjakan tidak dapat dihapus."` Hapus = soft delete; nomor urut dirapatkan ulang. |
| R1.7 | **Urutan:** developer dapat memindahkan posisi milestone `planned` (drag atau tombol naik/turun). Milestone non-`planned` tetap pada posisinya. Urutan hanya bersifat tampilan: **tidak ada kewajiban mengerjakan berurutan** dan klien dapat mereview hasil milestone mana pun yang `submitted`. |
| R1.8 | Menambah milestone baru ketika sebagian sudah `approved` diperbolehkan (selama proyek belum `done`); persentase proyek dihitung ulang (R2.14). |
| R1.9 | Setiap perubahan milestone (buat/ubah/hapus/urut) dicatat log `milestone.created|updated|deleted|reordered` dan memicu event `MilestoneChanged`. |

## 4. Aturan bisnis — Update progres dan hasil

| ID | Aturan |
|---|---|
| R2.1 | Hanya developer yang ditugaskan boleh mengirim update, dan hanya bila proyek punya ≥ 1 milestone (`409 "Susun milestone terlebih dahulu."`), Workspace aktif (K3), dan status proyek `approved` atau `process`. Klien dan staf lain `403`. |
| R2.2 | Update **selalu terkait satu milestone** (`milestone_id` wajib, milik proyek yang sama). Milestone `approved` tidak dapat menerima update: `409 "Milestone ini sudah disetujui."` Milestone `submitted` tidak dapat menerima update baru: `409 "Milestone sedang menunggu review klien."` |
| R2.3 | Dua jenis update: **`progress`** dan **`result`** (hasil). |
| R2.4 | **Progres:** `percent` bilangan bulat **0–99**, harus **≥** persentase milestone saat ini (tidak boleh turun) → `422 "Persentase tidak boleh lebih kecil dari progres terakhir ({n}%)."` Milestone `planned` menjadi `in_progress`. |
| R2.5 | **Hasil:** tidak memakai input `percent`; sistem menetapkan **100**. Milestone menjadi `submitted`; update itu `review_status = pending`. |
| R2.6 | Setelah revisi diminta (Fitur 3) milestone berstatus `revision` dan persentasenya diatur **90**; developer boleh mengirim progres 90–99 (→ `in_progress`) atau langsung hasil baru (→ `submitted`). |
| R2.7 | Hanya **satu hasil `pending` per milestone**; dijamin oleh R2.2 dan dicek ulang di dalam transaksi. |
| R2.8 | `note` wajib 10–2000 karakter. Hasil wajib `title` 3–100. Progres tidak memakai `title`. |
| R2.9 | Hasil wajib melampirkan **minimal satu** berkas atau tautan. Progres boleh tanpa keduanya. |
| R2.10 | Berkas per update: maks **10 berkas**, masing-masing **25 MB**, total **100 MB**; tipe `pdf`, `png`, `jpg/jpeg`, `zip`, diperiksa dari isi berkas (MIME) di server. |
| R2.11 | Tautan: maks 5 per update, wajib `https://`, maks 500 karakter; ditampilkan sebagai teks tertaut dengan `rel="noopener noreferrer nofollow"` dan `target="_blank"`. |
| R2.12 | Update **tidak dapat diedit atau dihapus** (K11). Koreksi lewat update baru atau komentar. |
| R2.13 | Update pertama yang berhasil pada proyek berstatus `approved` memindahkan proyek ke `process` (log `status.changed`, actor sistem) di **transaksi yang sama**. |
| R2.14 | `projects.progress_percent` = **rata-rata bulat (dibulatkan) persentase semua milestone** (bobot sama; milestone `planned` = 0, `approved` = 100). Dihitung ulang tiap kali milestone atau update berubah, dalam transaksi yang sama. |
| R2.15 | Seluruh pembuatan update (baris + berkas + milestone + proyek + log) **atomik**; bila penyimpanan berkas gagal, tidak ada yang tersimpan dan berkas yang telanjur ditulis dihapus. |

## 5. Model data

**`project_milestones`**

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `project_id` | FK cascade, index | |
| `title` | string(100) | |
| `description` | text nullable | ≤ 1000 |
| `position` | unsigned smallint | 1..n unik per proyek |
| `due_date` | date nullable | |
| `status` | string(12) | `planned`, `in_progress`, `submitted`, `revision`, `approved` |
| `percent` | unsigned tinyint | 0–100 |
| `approved_at` | timestamp nullable | |
| `created_at`, `updated_at`, `deleted_at` | timestamp | soft delete |

**`project_updates`**

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `project_id` | FK cascade | |
| `milestone_id` | FK `project_milestones.id` | wajib |
| `user_id` | FK `users.id` | developer pengirim |
| `type` | string(10) | `progress` / `result` |
| `title` | string(100) nullable | wajib untuk `result` |
| `note` | text | 10–2000 |
| `percent` | unsigned tinyint | 0–100 |
| `links` | json | array URL; default `[]` |
| `review_status` | string(20) nullable | hanya `result`: `pending`, `approved`, `revision_requested` |
| `reviewed_at`, `reviewed_by` | timestamp / FK nullable | diisi Fitur 3–4 |
| `created_at` | timestamp | tanpa `updated_at` |

Berkas: `project_attachments` dengan `update_id`, disimpan di disk privat `attachments/{project_id}/updates/{update_id}/{hash}.{ext}`.

## 6. API

| Metode & path | Akses | Keterangan |
|---|---|---|
| `GET /api/projects/{project}/milestones` | Peserta | Milestone + status + persentase + jumlah update |
| `POST /api/projects/{project}/milestones` | Developer | `{ title, description?, due_date? }` |
| `PATCH /api/milestones/{milestone}` | Developer | Field sesuai R1.5 |
| `DELETE /api/milestones/{milestone}` | Developer | R1.6 |
| `PATCH /api/projects/{project}/milestones/order` | Developer | `{ "ids": [3,1,2] }` — hanya milestone `planned`; harus memuat semua id `planned` |
| `POST /api/milestones/{milestone}/updates` | Developer | `multipart/form-data` (di bawah) |
| `GET /api/milestones/{milestone}/updates` | Peserta | `before_id`, `limit`; terbaru dulu |
| `GET /api/attachments/download/{attachment}` | Peserta | endpoint yang ada, kebijakan diperluas |

**`POST` update — field:** `type` (`progress`/`result`, wajib), `note` (wajib), `percent` (hanya `progress`, 0–99), `title` (hanya `result`), `links[]` (≤ 5), `files[]` (≤ 10).

Balasan `201`:

```json
{ "status": "success", "message": "Update berhasil dikirim",
  "data": { "id": 6, "milestone_id": 2, "type": "result", "title": "Desain halaman utama", "note": "...",
            "percent": 100, "links": ["https://staging.contoh.test"], "review_status": "pending",
            "author": { "id": 12, "name": "Sari" },
            "files": [ { "id": 31, "file_name": "desain.zip", "file_size": 1048576, "file_type": "application/zip" } ],
            "created_at": "2026-10-28T14:05:00+07:00" },
  "milestone": { "id": 2, "status": "submitted", "percent": 100 },
  "project": { "status": "process", "progress_percent": 40 } }
```

**Pesan error baku:**

| Kondisi | Kode | Pesan |
|---|---|---|
| Bukan developer proyek | 403 | "Anda tidak ditugaskan pada proyek ini." |
| Klien mengubah milestone/update | 403 | "Hanya developer proyek yang dapat melakukan ini." |
| Pembayaran belum terkonfirmasi | 409 | "Workspace aktif setelah pembayaran terkonfirmasi." |
| Belum ada milestone | 409 | "Susun milestone terlebih dahulu." |
| Milestone `approved` / `submitted` | 409 | "Milestone ini sudah disetujui." / "Milestone sedang menunggu review klien." |
| Status proyek tidak valid | 409 | "Proyek tidak dapat menerima update pada status ini." |
| Persentase turun | 422 | "Persentase tidak boleh lebih kecil dari progres terakhir ({n}%)." |
| Hasil tanpa berkas/tautan | 422 | "Sertakan minimal satu berkas atau tautan hasil pengerjaan." |
| Tipe/ukuran berkas | 422 | "Tipe berkas harus PDF, PNG, JPG, atau ZIP." / "Setiap berkas maksimal 25 MB." |
| Milestone melebihi batas | 422 | "Maksimal 10 milestone per proyek." |

## 7. Realtime dan notifikasi

- `MilestoneChanged` → `.milestone.changed` (payload: milestone + `project.progress_percent`).
- `ProjectUpdateSubmitted` → `.update.created` (payload = `data` di atas, tanpa isi berkas).
- `ProjectUpdated` (ada) dikirim bila status/persentase proyek berubah agar daftar Orderan ter-update.
- Log: `milestone.*`, `update.progress` (`meta`: milestone, percent), `update.result` (`meta`: milestone, title), `status.changed` bila R2.13 terpicu.
- Email: hasil → klien (README §10). Progres biasa tidak mengirim email.

## 8. UI (tab Milestone)

**Developer:**
- Bila belum ada milestone: kartu ajakan "Susun milestone proyek" + tombol Tambah. Daftar milestone dengan tombol naik/turun, ubah, hapus (hanya yang diizinkan), tenggat.
- Tiap milestone berupa kartu yang bisa dibuka: status (lencana), bar persentase, daftar update (terbaru dulu), dan **form** (progres/hasil) bila R2.1–R2.2 terpenuhi; bila tidak, form dinonaktifkan dengan **alasan teks** (bukan disembunyikan).
- Konfirmasi saat mengirim hasil: "Setelah dikirim, milestone menunggu review klien dan tidak dapat menerima update baru."

**Klien:** daftar milestone yang sama tanpa form/tombol ubah; kartu `submitted` menonjolkan tombol **Tinjau Hasil** (Fitur 3–4). Bar persentase proyek di kepala Workspace. **Daftar Orderan klien** memakai `progress_percent` nyata.

## 9. Kasus tepi

| Situasi | Perilaku |
|---|---|
| Developer diganti | Developer baru melanjutkan semua milestone; update lama tetap dengan nama pengirim asli. |
| Dua update bersamaan | Validasi persentase/status dalam transaksi dengan `lockForUpdate` pada baris milestone; yang kedua dapat `422/409`. |
| MIME berbeda dari ekstensi | Ditolak `422`. |
| Menambah milestone setelah 100% | Persentase proyek turun sesuai R2.14; bila status proyek `done` ditolak (R1.2). |
| Proyek `pending`/`rejected`/belum dibayar | Tab Milestone menampilkan keadaan kosong sesuai K3; tanpa form. |
| Batas ukuran server | `upload_max_filesize`, `post_max_size`, `client_max_body_size` ≥ 100 MB (perbarui `docs/deployment.md`). |

## 10. Kriteria penerimaan

- [ ] Hanya developer ditugaskan yang dapat mengelola milestone dan mengirim update; klien dan staf lain (termasuk admin yang tidak ditugaskan) `403`.
- [ ] Update ditolak tanpa milestone, sebelum dibayar, pada milestone `approved`/`submitted`, atau pada status proyek tidak valid — dengan pesan baku.
- [ ] Milestone: maks 10; edit/hapus/urut sesuai R1.5–R1.7; judul terkunci setelah dikerjakan.
- [ ] Progres tidak bisa turun; setelah revisi dasar 90.
- [ ] Hasil menetapkan milestone `submitted`, percent 100, `review_status = pending`; wajib berkas atau tautan.
- [ ] Update pertama memindahkan proyek `approved → process` otomatis.
- [ ] `progress_percent` proyek = rata-rata persentase milestone dan konsisten setelah setiap perubahan.
- [ ] Kegagalan di tengah proses tidak meninggalkan baris/berkas yatim.
- [ ] Klien menerima email saat hasil dikirim; tidak untuk progres biasa.
- [ ] Daftar milestone/update dan bar progres klien ter-update tanpa refresh.
- [ ] Tidak ada endpoint ubah/hapus update.

## 11. Rencana test

Feature per aturan R1.1–R2.15 (izin per peran termasuk admin dan developer lain, validasi, transaksi atomik dengan paksa gagal simpan berkas, `lockForUpdate`, batas laju), IDOR unduh berkas, `Event::fake` untuk `MilestoneChanged`/`ProjectUpdateSubmitted`/`ProjectUpdated`, perhitungan `progress_percent` (beberapa kombinasi), dan alur browser developer→klien.
