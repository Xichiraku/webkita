# Fitur 6 dan 7 — Riwayat Aktivitas dan Status Proyek

Prasyarat: [README.md](README.md) (K2–K5, K8, K10, §5 status). Dokumen PA 2.2.4 dan 4.1.7–4.1.8: seluruh aktivitas komunikasi dan pengerjaan dicatat agar perkembangan proyek dapat ditelusuri; status proyek ditampilkan kepada klien dan developer.

## 1. Tujuan

- **Riwayat aktivitas:** jejak append-only tentang siapa melakukan apa dan kapan, tampil sebagai timeline di Workspace.
- **Status proyek:** status terkini yang jelas, berpindah menurut aturan README §5, dengan setiap perubahan tercatat.

## 2. Cakupan

**Termasuk:** tabel log, pencatat tunggal `ProjectActivityLogger`, titik pemanggilan, endpoint baca, timeline, realtime, backfill data lama; tampilan dan aturan status proyek (README §5), perubahan UI admin (K5).

**Di luar cakupan:** log akses/login, ekspor, ubah/hapus log, notifikasi push, akses staf yang tidak ditugaskan ke riwayat (K4).

## 3. Aturan bisnis — Riwayat aktivitas

| ID | Aturan |
|---|---|
| R6.1 | Log **append-only**: tidak ada endpoint ubah/hapus; baris tidak pernah di-update. |
| R6.2 | Setiap aktivitas punya `type` dari daftar §4. Tipe di luar daftar ditolak pencatat (`InvalidArgumentException`). |
| R6.3 | Aktivitas dicatat **dalam transaksi yang sama** dengan perubahan datanya; transaksi gagal → log tidak tercatat. |
| R6.4 | `actor_id` = pengguna pelaku; `null` = **sistem** (tampil "Sistem"). Pemanggil aksi sistem **wajib** memakai `logSystem()`. |
| R6.5 | Semua aktivitas terlihat oleh **kedua peserta** (klien dan developer yang ditugaskan). Tidak ada tingkat visibilitas; staf yang tidak ditugaskan tidak melihat (K4). |
| R6.6 | Perubahan status dicatat **sekali per perubahan** dengan `from` dan `to`; memilih status yang sama tidak mencatat apa pun. |
| R6.7 | Aktivitas yang memicu aktivitas lain (mis. hasil terkirim memicu `status.changed`) dicatat sebagai **baris terpisah berurutan**. |
| R6.8 | Timeline tampil **terlama di atas → terbaru di bawah**; muat awal 30 terbaru, tombol "Muat sebelumnya". |
| R6.9 | Teks dirender dari `type` + `meta` oleh satu `ActivityPresenter`; teks tidak disimpan di database. |
| R6.10 | Pengguna yang di-soft-delete tampil dengan nama terakhir; bila baris pengguna hilang total: "Pengguna terhapus". |
| R6.11 | Komentar dan pesan chat biasa **tidak** masuk timeline (hanya `chat.started`). |

## 4. Daftar tipe aktivitas

| `type` | Dipicu oleh | `meta` | Tampil sebagai |
|---|---|---|---|
| `project.created` | Klien mengajukan proyek | `{plan}` | "Budi mengajukan proyek (paket Starter)." |
| `status.changed` | Perubahan status oleh admin atau sistem | `{from,to,reason}` | "Status berubah: Disetujui → Dalam Proses." |
| `price.set` | Admin menetapkan harga | `{amount,invoice_number}` | "Harga final ditetapkan Rp 3.000.000 (INV-0012-2026)." |
| `payment.paid` | Webhook menandai lunas (pembayaran terkonfirmasi) | `{order_id,amount,method}` | "Pembayaran Rp 3.000.000 diterima. Workspace aktif." |
| `developer.assigned` | Admin menugaskan/mengganti | `{developer_id,developer_name,previous_name}` | "Sari ditugaskan sebagai developer." |
| `developer.unassigned` | Admin melepas | `{developer_name}` | "Sari tidak lagi menjadi developer proyek ini." |
| `milestone.created` / `.updated` / `.deleted` / `.reordered` | Developer | `{milestone_id,title}` | "Sari menambahkan milestone “Desain”." |
| `update.progress` | Developer kirim progres | `{milestone_id,milestone_title,update_id,percent}` | "Sari memperbarui “Desain” menjadi 40%." |
| `update.result` | Developer kirim hasil | `{milestone_id,milestone_title,update_id,title}` | "Sari menyerahkan hasil “Desain”." |
| `review.approved` | Klien menyetujui | `{milestone_id,milestone_title,update_id}` | "Budi menyetujui “Desain”." |
| `review.revision_requested` | Klien minta revisi | `{milestone_id,milestone_title,update_id,revision_number}` | "Budi meminta revisi #1 untuk “Desain”." |
| `revision.limit_changed` | Kuota revisi diubah | `{from,to}` | "Kuota revisi diubah 3 → 5." (atau "→ tanpa batas") |
| `chat.started` | Pesan chat pertama | `{message_id}` | "Percakapan dimulai." |

Aktivitas `project.created`, `status.changed` (oleh admin), `price.set`, `payment.paid`, dan `developer.*` dicatat **sejak sebelum Workspace aktif**, tetapi baru tampil setelah Workspace aktif (K3).

## 5. Model data

**`project_activities`**: `id` (PK); `project_id` (FK cascade, index); `actor_id` (FK `users`, nullable, nullOnDelete); `type` (string 40); `meta` (json, objek); `created_at` (tanpa `updated_at`). Indeks `(project_id, id)`. Model `ProjectActivity` (`UPDATED_AT = null`, cast `meta`, `$fillable` kosong).

**`App\Services\ProjectActivityLogger`:** `log(Project $project, string $type, array $meta = [], ?User $actor = null)`, `logSystem(Project, string, array)`; setelah menyimpan men-dispatch `ProjectActivityCreated`.

Aturan arsitektur: `ProjectActivity::create` hanya boleh muncul di pencatat (dijaga test grep).

## 6. Titik pemanggilan (wajib)

`ProjectService::create` (`project.created`) · `ProjectService::updateStatus` hanya bila status berubah (`status.changed`) · `ProjectService::setPrice` (`price.set`) · `PaymentService::handleNotification` hanya bila berubah menjadi `paid` (`payment.paid`, sistem) · penugasan developer · semua aksi pada berkas 02, 03, 04.

## 7. API dan realtime

`GET /api/projects/{project}/activities` — akses peserta (K3/K9); `before_id`, `limit` (default 30, maks 50). Respons: array **terbaru dulu**, tiap elemen `{ id, type, actor:{id,name,role}|{id:null,name:"Sistem",role:"system"}, text, icon, meta, created_at }`, dengan `meta.has_more`. Bukan peserta (termasuk staf yang tidak ditugaskan) `403`; Workspace belum aktif `409`; proyek tidak ada `404`.

Event `ProjectActivityCreated` → `private-project.{id}`, siaran `.activity.created`, payload = satu elemen di atas. UI menambahkan baris di akhir; bila pengguna tidak di dasar daftar, tampil tombol "↓ Aktivitas baru".

## 8. Aturan bisnis — Status proyek (Fitur 7)

Aturan perpindahan ada di README §5. Tambahan:

| ID | Aturan |
|---|---|
| R7.1 | Perpindahan `approved → process` dan `process → done` hanya dilakukan **sistem** (README §5). Admin tidak dapat memilihnya di UI maupun API (`409 "Status ini diatur otomatis oleh sistem."`). |
| R7.2 | Admin hanya dapat: `pending → approved`, `pending → rejected`, `approved → rejected` (selama belum `process`). `rejected` final dan alasan opsional ≤ 500 karakter (sudah ada). |
| R7.3 | Setiap perubahan status (oleh admin atau sistem) mencatat `status.changed`; perubahan oleh sistem memakai actor `null`. |
| R7.4 | Status ditampilkan dengan chip yang sama dengan sekarang (`order-chip`); label klien: Menunggu, Disetujui, Dalam Proses, Selesai, Ditolak. Ringkasan tambahan di kepala Workspace: "{n} dari {m} milestone selesai · {p}%". |
| R7.5 | Status proyek terkini dibaca dari tabel `projects`, **bukan** dihitung dari log. |

## 9. UI

- **Timeline** di tab Ringkasan: daftar vertikal; tiap baris ikon bulat, teks, pelaku (atau "Sistem"), waktu relatif dengan `title` waktu absolut. Status lama → baru memakai `order-chip`. Kosong: "Belum ada aktivitas." Error: pesan + "Coba lagi". Aksesibilitas: `<ol>`, area `aria-live="polite"`.
- **Kepala Workspace:** chip status besar, bar persentase, nama developer, ringkasan milestone (R7.4).
- **Panel admin:** dropdown status pada tabel/modal hanya menampilkan opsi yang diizinkan (R7.2); kolom Developer (README §6).

## 10. Backfill

`php artisan activities:backfill` (idempoten; hanya untuk proyek tanpa log): `project.created` pada `created_at`; `price.set` bila `invoice_issued_at` terisi; `payment.paid` untuk tiap pembayaran `paid`; satu `status.changed` dari `pending` ke status kini (`meta.backfilled = true`, UI menambah "(riwayat awal tidak tersedia)").

## 11. Kasus tepi

| Situasi | Perilaku |
|---|---|
| Admin memilih status sama | Tidak ada log (R6.6), respons `200`. |
| Webhook Midtrans berulang | Log hanya sekali. |
| Dua admin ubah status bersamaan | Yang kalah `409` (perilaku sekarang); log hanya untuk yang menang. |
| Proyek soft-deleted | Log tersimpan, tidak dapat diakses. |
| Developer diganti | Aktivitas lama tetap menampilkan nama developer lama dari `meta`. |

## 12. Kriteria penerimaan

- [ ] Setiap titik pemanggilan §6 menghasilkan tepat satu baris log dengan `type`, `actor_id`, `meta` benar.
- [ ] Tipe di luar daftar melempar exception; rollback transaksi tidak meninggalkan log.
- [ ] Hanya klien pemilik dan developer ditugaskan yang membaca log (API dan channel); staf lain dan pihak lain `403`; Workspace belum aktif `409`.
- [ ] Timeline terlama→terbaru, "Muat sebelumnya" bekerja, aktivitas baru muncul tanpa refresh.
- [ ] Admin tidak bisa memilih `process`/`done` (UI dan API); `approved → process` dan `process → done` otomatis sesuai README §5 dan tercatat dengan actor sistem.
- [ ] Backfill dapat dijalankan dua kali tanpa duplikat.
- [ ] Tidak ada `ProjectActivity::create` di luar pencatat.

## 13. Rencana test

Unit `ActivityPresenter` per tipe; Feature tiap titik pemanggilan, paginasi, izin per peran (klien, developer ditugaskan, staf tidak ditugaskan, tamu), `Event::fake`, otorisasi channel, backfill, perubahan status admin (R7.1–R7.2) dan transisi otomatis.
