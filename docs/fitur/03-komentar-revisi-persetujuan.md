# Fitur 3 dan 4 — Komentar dan Revisi serta Persetujuan Hasil Pengerjaan

Prasyarat: [README.md](README.md) (K2–K13, status milestone) dan [02-milestone-progres-hasil.md](02-milestone-progres-hasil.md). Dokumen PA 2.2.4: komentar dan permintaan revisi dipakai untuk **menanggapi hasil pengerjaan** yang dikirim developer; keputusan akhir di tangan klien.

## 1. Tujuan

Klien menanggapi **hasil** sebuah milestone: berdiskusi lewat komentar, **meminta revisi** dengan alasan tertulis, atau **menyetujui**. Developer menanggapi dan memperbaiki. Bila semua milestone disetujui, proyek selesai.

## 2. Cakupan

**Termasuk:** komentar (umum atau terkait satu update), permintaan revisi dan kuota, persetujuan per milestone, penyelesaian proyek otomatis, hapus komentar, realtime tambahan, email.

**Di luar cakupan:** komentar bersarang/balasan, mention, reaksi, edit komentar, lampiran pada komentar (berkas lewat update atau chat), persetujuan parsial per butir, persetujuan otomatis karena waktu, peran admin terpisah (K4).

## 3. Konsep

- **Hasil** = update `result`; punya `review_status`: `pending`, `approved`, `revision_requested`.
- **Review** hanya terhadap hasil `pending` pada milestone `submitted`.
- **Revisi** = baris `project_revisions` bernomor per **proyek** (#1, #2, …), `open` sampai developer mengirim hasil pengganti pada milestone itu, lalu `resolved`. **Kuota revisi** berlaku untuk seluruh proyek (README K13).
- **Persetujuan** = klien menyetujui satu hasil → milestone `approved` (final).

## 4. Aturan bisnis — Komentar

| ID | Aturan |
|---|---|
| R3.1 | Klien pemilik dan developer yang ditugaskan boleh berkomentar saat Workspace aktif, pada status proyek apa pun kecuali `rejected` (tidak punya Workspace). Staf yang tidak ditugaskan (termasuk admin) dan pihak lain `403`. |
| R3.2 | Komentar berupa teks polos 1–2000 karakter setelah `trim`. HTML tidak diizinkan; ditampilkan sebagai teks (di-escape) dengan baris baru dipertahankan. |
| R3.3 | Komentar boleh ditautkan ke **satu update** milik proyek yang sama (`update_id`); update proyek lain → `422`. Tanpa `update_id` = komentar umum. |
| R3.4 | Komentar tidak dapat diedit (K11). |
| R3.5 | **Hapus:** hanya **pemilik komentar** dalam **15 menit** sejak dibuat (`403 "Waktu menghapus komentar sudah lewat."` setelahnya). Soft delete; tampil sebagai "Komentar ini telah dihapus"; isinya tidak lagi dikirim ke UI/API. |
| R3.6 | Komentar milik pihak lain tidak dapat dihapus oleh siapa pun, termasuk admin (K4). |

## 5. Aturan bisnis — Revisi

| ID | Aturan |
|---|---|
| R3.7 | Hanya **klien pemilik** dapat meminta revisi. Developer `403 "Hanya pemilik proyek yang dapat meninjau hasil."` |
| R3.8 | Hanya pada hasil `pending` milik milestone `submitted`; selain itu `409 "Tidak ada hasil yang sedang menunggu review."` |
| R3.9 | `reason` wajib 20–2000 karakter, teks polos: `422 "Jelaskan bagian yang perlu direvisi (minimal 20 karakter)."` |
| R3.10 | Hanya bila kuota proyek belum habis: `revision_limit` kosong (**tanpa batas**) atau `revisions_used < revision_limit`. Bila habis: `409 "Kuota revisi proyek ({limit}) sudah habis. Setujui hasil atau sepakati tambahan kuota dengan developer."` |
| R3.11 | Saat revisi diminta (**satu transaksi**): buat `project_revisions` (`number = projects.revisions_used + 1`, `open`); `projects.revisions_used += 1`; hasil → `revision_requested` (+ `reviewed_at/by`); milestone → `revision`, `percent = 90`; hitung ulang `projects.progress_percent`; log `review.revision_requested`; email ke developer; event `UpdateReviewed`. |
| R3.12 | Alasan revisi otomatis diposting sebagai komentar pada hasil itu (pengirim = klien). |
| R3.13 | Saat developer mengirim hasil baru pada milestone `revision`, revisi `open` milestone itu menjadi `resolved` (`resolved_by_update_id`, `resolved_at`). Progres 90–99 pada milestone `revision` **tidak** menutup revisi. |
| R3.14 | **Kuota revisi disepakati dengan klien** dan diatur per proyek: **admin atau developer yang ditugaskan** dapat mengubah `projects.revision_limit` kapan saja lewat `PATCH /api/projects/{project}/revision-limit` dengan body `{ "limit": 5 }` (0–50) atau `{ "limit": null }` (tanpa batas). Nilai tidak boleh lebih kecil dari `revisions_used` (`422 "Kuota tidak boleh lebih kecil dari revisi yang sudah dipakai ({n})."`). Klien tidak dapat mengubahnya. Perubahan dicatat `revision.limit_changed` dan terlihat oleh klien. |

## 6. Aturan bisnis — Persetujuan

| ID | Aturan |
|---|---|
| R4.1 | Hanya **klien pemilik** dapat menyetujui, hanya pada hasil `pending` milik milestone `submitted`. |
| R4.2 | Saat disetujui (**satu transaksi**): hasil → `approved` (+ `reviewed_at/by`); milestone → `approved`, `percent = 100`, `approved_at = now`; hitung ulang `progress_percent`; log `review.approved`; email ke developer; event `UpdateReviewed`. |
| R4.3 | Persetujuan **final**: milestone `approved` tidak dapat dibuka kembali. UI mengonfirmasi: "Dengan menyetujui, milestone ini dinyatakan selesai dan tidak dapat dibuka kembali." |
| R4.4 | Bila milestone yang disetujui adalah yang **terakhir** (semua milestone aktif `approved`, jumlah ≥ 1): proyek → `done` (K7), `completed_at = now`, `progress_percent = 100`, log `status.changed` (actor sistem) dan event `ProjectUpdated`, email "Proyek selesai" ke klien dan developer. Dalam transaksi yang sama dengan R4.2. |
| R4.5 | Tidak ada persetujuan otomatis dan tidak ada penyelesaian paksa oleh admin (K4, Q6). |
| R4.6 | Menyetujui dua kali (klik ganda) → `409 "Tidak ada hasil yang sedang menunggu review."` Persetujuan dan pengiriman hasil bersamaan dilindungi `lockForUpdate` pada milestone. |

## 7. Model data

**`project_comments`**: `id`; `project_id` (FK cascade); `update_id` (FK `project_updates`, nullable, nullOnDelete); `user_id` (FK); `body` (text, 1–2000); `created_at`; `deleted_at`, `deleted_by` (nullable). Indeks `(project_id, id)`, `(update_id)`.

**`project_revisions`**: `id`; `project_id` (FK cascade); `milestone_id` (FK); `update_id` (FK, hasil yang diminta direvisi); `number` (unsigned tinyint, unik per `(project_id, number)`); `reason` (text); `requested_by` (FK); `status` (`open`/`resolved`); `resolved_by_update_id` (FK nullable); `created_at`, `resolved_at`.

## 8. API

| Metode & path | Akses | Keterangan |
|---|---|---|
| `GET /api/projects/{project}/comments` | Peserta | `before_id`, `limit` (default 30, maks 50), `update_id` opsional |
| `POST /api/projects/{project}/comments` | Peserta | `{ "body": "...", "update_id": 6 }` |
| `DELETE /api/comments/{comment}` | Pemilik ≤ 15 menit | Soft delete |
| `POST /api/updates/{update}/approve` | Klien | Tanpa body |
| `POST /api/updates/{update}/request-revision` | Klien | `{ "reason": "..." }` |
| `GET /api/projects/{project}/revisions` | Peserta | Daftar revisi proyek (nomor, milestone, alasan, status) |
| `PATCH /api/projects/{project}/revision-limit` | Admin atau developer ditugaskan | R3.14 |

Komentar:

```json
{ "id": 9, "update_id": 6, "body": "Logo masih salah warna.", "deleted": false,
  "author": { "id": 3, "name": "Budi", "role": "user" }, "can_delete": true,
  "created_at": "2026-10-28T14:10:00+07:00" }
```

Komentar terhapus: `body = null`, `deleted = true`. `can_delete` dihitung server (R3.5).

`approve` / `request-revision` mengembalikan `data.update.review_status`, `data.milestone` (`status`, `percent`), dan `data.project` (`status`, `progress_percent`, `revisions_used`, `revision_limit`).

Pesan error baku: lihat kolom Aturan R3.7–R3.10, R4.6; update bukan milik proyek peserta → `404`.

## 9. Realtime dan notifikasi

| Aksi | Event | Catatan |
|---|---|---|
| Komentar dibuat / dihapus | `.comment.created` / `.comment.deleted` | Tambahan (dokumen PA mewajibkan realtime hanya untuk chat) |
| Review (setuju/revisi) | `.update.reviewed` | Payload `{ update_id, review_status, milestone, project }` |
| Status proyek berubah | `ProjectUpdated` | Daftar Orderan ter-update |

Email: lihat README §10 (revisi diminta, milestone disetujui, proyek selesai). Komentar tidak mengirim email.

## 10. UI (tab Milestone dan Diskusi)

- **Tab Milestone:** pada kartu milestone `submitted` (klien) tampil panel **Tinjau Hasil**: judul, catatan, berkas, tautan, dan dua tombol **Setujui Hasil** (primer) dan **Minta Revisi** (sekunder). Minta Revisi membuka modal: kolom alasan (penghitung ≥ 20), "Sisa revisi: 2 dari 3" (atau "Revisi: tanpa batas"), tombol Kirim. Kuota habis → tombol dinonaktifkan dengan teks R3.10. Developer melihat status yang sama tanpa tombol ("Menunggu review klien", "Revisi #2 sedang dikerjakan").
- **Tab Diskusi:** daftar komentar terlama→terbaru; komentar bertaut update menampilkan "Tentang: {milestone} · {judul update}" yang menautkan ke kartu itu; filter "Semua" / "Per milestone". Kolom tulis (Kirim; `Ctrl/⌘+Enter`), penghitung 2000, pilihan "Tentang: (umum) / {update}". Komentar terhapus abu-abu. Tombol hapus hanya bila `can_delete`.
- Lencana pada tab Milestone: titik bila ada hasil `pending` (klien) atau revisi `open` (developer).
- Setelah proyek `done`: banner "Proyek selesai"; semua tombol review hilang; komentar baru tetap diizinkan.

## 11. Kasus tepi

| Situasi | Perilaku |
|---|---|
| Developer diganti saat revisi `open` | Tetap `open`; developer baru menutupnya dengan hasil baru. |
| Kuota revisi habis | Klien hanya dapat menyetujui, atau menyepakati tambahan kuota dengan developer (R3.14). |
| Milestone baru ditambah setelah semua disetujui | Proyek sudah `done` → ditolak (README R1.2). Bila belum `done`, proyek menunggu milestone baru. |
| Klien tidak merespons hasil | Tidak ada otomatisasi (Q6). |
| Klien dihapus | Komentarnya tetap tampil dengan nama terakhir. |
| Pemilik menghapus komentar menit ke-16 | `403` (R3.5). |

## 12. Kriteria penerimaan

- [ ] Klien dan developer dapat berkomentar (umum/per update); staf yang tidak ditugaskan dan pihak lain `403`; isi tidak dirender sebagai HTML.
- [ ] Hapus komentar hanya pemilik dalam 15 menit; isi komentar terhapus tidak dikirim.
- [ ] Hanya klien yang dapat menyetujui/meminta revisi; developer dan staf lain `403`.
- [ ] Minta revisi: kuota **proyek** berkurang, milestone `revision`, percent 90, komentar otomatis, email ke developer, log, proyek tetap `process`.
- [ ] Kuota habis memblokir revisi dengan pesan R3.10; kuota kosong berarti tanpa batas.
- [ ] Setuju: milestone `approved` final, persentase proyek dihitung ulang.
- [ ] Milestone terakhir disetujui → proyek `done` otomatis (atomik dengan persetujuan), email "Proyek selesai".
- [ ] Hasil baru menutup revisi `open` milestone itu; progres biasa tidak.
- [ ] Klik ganda dan balapan setuju vs kirim hasil menghasilkan `409`, bukan data ganda.
- [ ] Perubahan tampil di dua sesi tanpa refresh.

## 13. Rencana test

Feature per aturan R3.1–R4.6 (izin per peran termasuk admin), atomisitas (paksa error sesudah `revisions_used++` → tidak ada perubahan), balapan, sanitasi `body`, `Event::fake` untuk `UpdateReviewed`/`ProjectCommentPosted`/`ProjectCommentDeleted`/`ProjectUpdated`, IDOR; browser: klien minta revisi → developer kirim ulang → klien setuju semua milestone → proyek selesai.
