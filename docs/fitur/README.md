# Spesifikasi Modul Project Workspace

Sumber kebutuhan: dokumen Proyek Akhir **"Implementasi Modul Project Workspace pada Platform WEBKITA untuk Pengelolaan dan Pemantauan Progres Proyek"** (Yulia Nabila, 3312411081). Dokumen ini menerjemahkannya menjadi spesifikasi implementasi pada aplikasi Laravel yang sudah ada. Baca ini dulu; berkas fitur memakai keputusan di sini.

## 0. Tujuh fitur modul dan pemetaannya

Dokumen PA menetapkan **tujuh fitur** (Batasan Masalah no. 3). Pemetaan ke berkas spesifikasi dan ke subbab dokumen:

| # | Fitur | Berkas spesifikasi | Subbab implementasi / pengujian di dokumen PA |
|---|---|---|---|
| 1 | Milestone | [02-milestone-progres-hasil.md](02-milestone-progres-hasil.md) | 4.1.2 / 4.2.3 |
| 2 | Submit progres dan hasil pengerjaan | [02-milestone-progres-hasil.md](02-milestone-progres-hasil.md) | 4.1.3 / 4.2.3 |
| 3 | Komentar dan revisi | [03-komentar-revisi-persetujuan.md](03-komentar-revisi-persetujuan.md) | 4.1.4 / 4.2.5 |
| 4 | Persetujuan (approval) hasil pengerjaan | [03-komentar-revisi-persetujuan.md](03-komentar-revisi-persetujuan.md) | 4.1.5 / 4.2.5 |
| 5 | Chat klien dan developer | [04-chat-klien-developer.md](04-chat-klien-developer.md) | 4.1.6 / 4.2.4 |
| 6 | Riwayat aktivitas | [01-riwayat-aktivitas-dan-status.md](01-riwayat-aktivitas-dan-status.md) | 4.1.7 / 4.2.6 |
| 7 | Status proyek | [01-riwayat-aktivitas-dan-status.md](01-riwayat-aktivitas-dan-status.md) §Status proyek | 4.1.8 |

Wadah semuanya adalah **Project Workspace** (§7) — subbab 4.1.1 / 4.2.2.

**Urutan pengerjaan:** Fondasi (§6, §10 T0) → Fitur 6+7 (log + status) → Fitur 1+2 (milestone, progres, hasil) → Fitur 3+4 (komentar, revisi, persetujuan) → Fitur 5 (chat).

## 1. Batasan dari dokumen PA (mengikat)

| Batasan | Konsekuensi pada spesifikasi |
|---|---|
| Pengguna modul **dibatasi pada klien dan developer** yang terlibat di proyek (Bab I, 1.4 no. 2; 2.2.1) | **Admin dan developer adalah orang yang sama** (konfirmasi pemilik proyek). Tidak ada akses admin terpisah ke Workspace: yang bekerja di Workspace adalah **developer yang ditugaskan** (boleh ber-role `admin` atau `developer`, termasuk menugaskan diri sendiri). Staf yang tidak ditugaskan pada proyek itu tidak masuk Workspace-nya. |
| Workspace **dibuat untuk setiap proyek setelah klien memiliki akun dan DP terkonfirmasi**; status DP berasal dari modul pembayaran dan Workspace **hanya membacanya** (2.2.1, 2.2.8) | **DP diganti full payment** (konfirmasi pemilik proyek): Workspace aktif bila proyek **sudah dibayar penuh** (`Project::isPaymentConfirmed()`, K2). Modul Workspace tidak mengubah data pembayaran. |
| Modul Kalkulator Budget dan Integrasi Payment Gateway **tidak** dibahas (1.4 no. 4) | Tidak ada perubahan pada halaman Harga, form pengajuan, atau alur Midtrans selain membaca status. |
| Milestone adalah **unit pelaporan progres dan hasil** (2.2.3) | Seluruh progres dan hasil dikirim **per milestone**, bukan per proyek. |
| Komentar/revisi menanggapi hasil; **chat** untuk komunikasi langsung; semua dicatat di riwayat aktivitas; chat **real-time lewat Laravel Reverb**, pesan disimpan di basis data (2.2.4, 2.2.7) | Chat wajib realtime. Komentar/revisi realtime bersifat tambahan. Semua aktivitas utama masuk log. |
| Teknologi: Laravel, Eloquent, MySQL, Laravel Reverb (2.2.5–2.2.7) | Sama dengan aplikasi saat ini. |
| Pemeliharaan di luar cakupan; pengujian dengan data pengujian (1.4 no. 5–6) | Tidak ada persyaratan retensi/arsip; pengujian memakai seeder data uji. |

## 2. Kondisi aplikasi saat ini (titik awal)

- Role: `user` (klien), `developer`, `admin`. Developer baru bisa **membaca** (orderan, pengguna, transaksi, paket). Belum ada penugasan developer ke proyek.
- Status proyek: `pending → approved → process → done`, dan `rejected` (final); admin mengubah semuanya secara manual; hanya status terakhir yang disimpan.
- Pembayaran: satu transaksi Midtrans untuk **harga final penuh** setelah status `approved` dan harga ditetapkan. Belum ada konsep DP terpisah.
- Lampiran: `project_attachments` (PDF/PNG/JPG ≤ 10 MB) hanya untuk berkas dari klien saat pengajuan.
- Realtime: Reverb dengan `ProjectUpdated`, `NewProjectCreated`, `PaymentStatusChanged` pada channel `App.Models.User.{id}` dan `admin.orders`.
- Istilah UI: "Orderan" (daftar) dan "Proyek". Dalam dokumen ini **proyek = orderan = `Project`**.

## 3. Glosarium

| Istilah | Arti |
|---|---|
| Klien | Pengguna ber-role `user`, pemilik proyek (`projects.user_id`). |
| Developer | Staf (user ber-role `developer` **atau `admin`** — satu orang yang sama) yang **ditugaskan** pada proyek (`projects.developer_id`). Staf lain tidak punya akses ke workspace proyek itu. |
| Peserta | **Klien pemilik dan developer yang ditugaskan.** Hanya mereka yang menggunakan Workspace. |
| Pembayaran terkonfirmasi | `Project::isPaymentConfirmed()` = proyek memiliki pembayaran berstatus `paid` (**full payment**, K2). |
| Workspace | Halaman kerja satu proyek (§7), aktif setelah pembayaran terkonfirmasi. |
| Milestone | Tahapan pekerjaan bernomor urut pada satu proyek; unit pelaporan (Fitur 1–2). |
| Update | Satu kiriman developer pada sebuah milestone: **progres** atau **hasil**. |
| Hasil | Update berjenis `result`; menyerahkan milestone untuk direview klien. |
| Review | Keputusan klien atas satu hasil: `approved` atau `revision_requested`. |
| Revisi | Permintaan perbaikan tertulis dari klien atas satu hasil. |

## 4. Keputusan desain bersama

| ID | Keputusan | Dasar |
|---|---|---|
| K1 | Satu proyek punya **paling banyak satu** developer (`projects.developer_id`, boleh kosong), dikonfirmasi pemilik proyek. Yang dapat ditugaskan: user aktif ber-role `developer` atau `admin`. Hanya **admin** yang menugaskan/mengganti/melepas, termasuk **menugaskan diri sendiri** ("Ambil proyek"). | Konfirmasi pemilik proyek. |
| K2 | **Pembayaran terkonfirmasi := ada pembayaran `paid` pada proyek (full payment).** Satu metode `Project::isPaymentConfirmed()`; bila kelak berubah (mis. DP persentase) cukup mengubah metode ini. | Konfirmasi pemilik proyek (DP diganti full payment). |
| K3 | Sebelum pembayaran terkonfirmasi, Workspace **tidak tersedia**: rute workspace menampilkan halaman informasi "Workspace aktif setelah pembayaran terkonfirmasi" (klien dengan tautan ke invoice/bayar; developer dengan keterangan "Menunggu pembayaran klien") dan semua API workspace menjawab `409` dengan pesan sama. | 2.2.1 |
| K4 | **Tidak ada akses admin terpisah ke Workspace.** Peran admin dan developer sama, jadi hak kerja Workspace mengikuti **penugasan** (K1), bukan role. Admin tanpa penugasan tidak masuk Workspace; ia tetap mengurus sisi platform (menyetujui/menolak pengajuan, menetapkan harga, menugaskan, mengatur kuota revisi). | Konfirmasi pemilik proyek. |
| K5 | Status proyek **tidak ditambah** (tetap `pending, approved, process, done, rejected`). Perpindahan `approved → process` dan `process → done` menjadi **otomatis oleh sistem** (§5); admin tidak lagi memindahkan keduanya secara manual. Admin tetap mengatur `pending → approved/rejected` dan `approved → rejected`. | Status "menunggu review" dibawa oleh status **milestone**, bukan status proyek. |
| K6 | **Milestone disusun oleh developer yang ditugaskan** (minimal 1, maksimal 10) sebelum mengirim update apa pun. Klien melihat, tidak mengubah. | Q2 (bawaan). |
| K7 | Proyek menjadi `done` **otomatis** saat **semua** milestone berstatus `approved` dan jumlah milestone ≥ 1. | Persetujuan oleh klien adalah penentu selesai. |
| K8 | Semua perubahan penting dicatat lewat satu pintu: `ProjectActivityLogger`. Fitur lain tidak menulis tabel log langsung. | Fitur 6. |
| K9 | Realtime: **satu channel privat per proyek `private-project.{id}`**, otorisasi hanya klien pemilik dan developer yang ditugaskan. Channel lama tetap untuk daftar Orderan. | 2.2.7 |
| K10 | Teks UI Bahasa Indonesia; kode, tabel, kolom, nilai enum Bahasa Inggris. Zona `Asia/Jakarta`; tampil `28 Okt 2026, 14:05`; API ISO 8601. | Konsisten dengan kode saat ini. |
| K11 | Tidak ada hapus permanen data kolaborasi oleh pengguna; "hapus" = soft delete yang tampil sebagai "dihapus". | Jejak penelusuran (1.1). |
| K12 | Batas laju per pengguna: kirim update 10/menit, komentar 20/menit, pesan chat 30/menit. | Mencegah spam. |
| K13 | **Kuota revisi ditetapkan per proyek sesuai kesepakatan dengan klien**: `projects.revision_limit` (0–50, atau kosong = **tanpa batas**) dan `projects.revisions_used`. Nilai awal proyek baru: `config('workspace.revision_limit')` = 3. Diubah oleh admin atau developer yang ditugaskan kapan saja (tidak boleh di bawah `revisions_used`), tercatat di log dan **terlihat oleh klien**. | Konfirmasi pemilik proyek. |

## 5. Status proyek dan status milestone

**Status proyek (Fitur 7):**

| Dari | Ke | Pemicu | Syarat |
|---|---|---|---|
| `pending` | `approved` / `rejected` | Admin | (sudah ada) |
| `approved` | `rejected` | Admin | Hanya selama **belum** `process` |
| `approved` | `process` | **Sistem**: update pertama (progres atau hasil) dari developer | pembayaran terkonfirmasi, developer ditugaskan, minimal 1 milestone |
| `process` | `done` | **Sistem**: milestone terakhir disetujui klien | Semua milestone `approved` (K7) |
| `done`, `rejected` | — | — | Final |

**Status milestone (Fitur 1–4):**

| Status | Arti (label UI) | Masuk dari | Pemicu |
|---|---|---|---|
| `planned` | Direncanakan | (dibuat) | Developer membuat milestone |
| `in_progress` | Dikerjakan | `planned`, `revision` | Developer mengirim **progres** |
| `submitted` | Menunggu review | `planned`, `in_progress`, `revision` | Developer mengirim **hasil** |
| `revision` | Revisi diminta | `submitted` | Klien meminta revisi |
| `approved` | Disetujui | `submitted` | Klien menyetujui (final) |

Perubahan kode wajib: tambah enum `MilestoneStatus`; ubah `ProjectStatus::allowedNext()` dan dropdown status admin (`resources/js/admin.js`, `orders-table.blade.php`) agar `approved→process` dan `process→done` tidak lagi tersedia manual; perbarui `ProjectService::updateStatus` dan test yang ada.

## 6. Penugasan developer (Fondasi)

- **Endpoint:** `PATCH /api/projects/{project}/developer` — body `{ "developer_id": 12 }` atau `null` untuk melepas. Hanya admin.
- **Validasi:** `developer_id` = user ber-role `developer` **atau `admin`**, `is_aktif = 'Y'`, tidak soft-deleted → bila tidak `422 "Developer tidak valid"`. Status proyek harus `approved` atau `process` → bila tidak `409 "Developer hanya dapat ditugaskan pada proyek yang sudah disetujui."`
- **Penggantian saat berjalan:** diperbolehkan. Milestone, update, komentar, pesan lama tetap utuh dengan nama pengirim aslinya. Developer lama kehilangan akses seketika.
- **Dampak:** log `developer.assigned` / `developer.unassigned`, event `ProjectUpdated`, email ke developer baru.
- **UI admin:** kolom "Developer" (dropdown staf aktif + Simpan) dan tombol **"Ambil proyek"** (menugaskan diri sendiri) pada modal detail orderan; kolom Developer di tabel.
- **UI developer/admin:** menu "Proyek Saya" (tampil untuk role `admin` dan `developer`) → `/developer/proyek`: daftar proyek yang ditugaskan, dengan status, persentase, lencana "Menunggu pembayaran" / "Perlu tindakan" (revisi diminta, chat belum dibaca). Halaman admin yang sudah ada tetap bisa dibaca developer seperti sekarang.

## 7. Halaman Project Workspace

| Peran | Rute | Nama rute |
|---|---|---|
| Klien | `/orderan/{project}` | `orderan.show` |
| Developer yang ditugaskan (role `developer` atau `admin`) | `/developer/proyek/{project}` | `developer.projects.show` |

Satu view bersama `pages/workspace.blade.php` dengan parameter peran. Staf yang bukan developer proyek itu mendapat `403` pada rute developer. Tab (urutan tetap, hash URL menyimpan tab aktif): **Ringkasan** (info proyek, status, persentase, developer, lampiran awal, riwayat aktivitas) · **Milestone** (daftar milestone, form developer, hasil, tombol review klien) · **Diskusi** (komentar) · **Chat** (dengan lencana belum dibaca). Akses: bukan peserta `403`; proyek tidak ada `404`; Pembayaran belum terkonfirmasi → halaman informasi K3. Daftar Orderan klien mendapat tombol "Buka Workspace".

## 8. Model data (ringkasan)

```
users ─┬─< projects (user_id = klien, developer_id)
       │      ├─< project_activities
       │      ├─< project_milestones ─< project_updates ─< project_attachments (update_id)
       │      │                │              └─< project_revisions
       │      ├─< project_comments (update_id opsional)
       │      ├─< chat_messages ─< project_attachments (message_id)
       │      └─< chat_reads
```

Kolom baru pada `projects`: `developer_id` (FK `users`, nullable, nullOnDelete), `assigned_at` (timestamp nullable), `progress_percent` (tinyint, 0), `completed_at` (timestamp nullable), `revision_limit` (unsigned tinyint nullable; kosong = tanpa batas; bawaan 3), `revisions_used` (unsigned tinyint, 0). Pada `project_attachments`: `update_id` (FK nullable, cascade), `message_id` (FK nullable, cascade), `uploaded_by` (FK `users` nullable); paling banyak satu dari `update_id`/`message_id` terisi.

## 9. Konvensi API dan realtime

- Semua endpoint di bawah `/api`, sesi login + CSRF, balasan `{status, message, data}`; error domain `{status:'error', message}` dengan `403` izin, `404` tidak ada, `409` aturan bisnis, `422` validasi (format Laravel). Pesan error Bahasa Indonesia dan spesifik.
- Paginasi cursor: `before_id`, `limit` (default 20, maks 50); balasan memuat `meta.has_more`.
- Peserta = klien pemilik atau developer ditugaskan **dan** pembayaran terkonfirmasi; otorisasi terpusat di `ProjectWorkspacePolicy` (`participate`, `isClient`, `isDeveloper`) dipakai semua controller/channel.
- Event broadcast: `ShouldBroadcast` + `ShouldDispatchAfterCommit`; payload minimal.
- Channel `project.{id}` (routes/channels.php): `participant($user, $project)` — klien pemilik atau developer ditugaskan, **tanpa** admin sebagai pihak terpisah (admin lolos hanya bila ia developer yang ditugaskan).

| Event | Siaran | Fitur |
|---|---|---|
| `ProjectActivityCreated` | `.activity.created` | 6 |
| `MilestoneChanged` | `.milestone.changed` | 1 |
| `ProjectUpdateSubmitted` | `.update.created` | 2 |
| `UpdateReviewed` | `.update.reviewed` | 3, 4 |
| `ProjectCommentPosted` / `ProjectCommentDeleted` | `.comment.created` / `.comment.deleted` | 3 |
| `ChatMessageSent` / `ChatMessageDeleted` | `.message.created` / `.message.deleted` | 5 |
| `ChatUnreadChanged` | `.chat.unread` (channel `App.Models.User.{id}`) | 5 |
| `ProjectUpdated` (ada; payload + `developer_id`, `progress_percent`) | `.project.updated` | 6, 7 |

## 10. Notifikasi email (antrian; kegagalan tidak menggagalkan aksi)

| Pemicu | Penerima | Subjek |
|---|---|---|
| Developer ditugaskan | Developer | "Anda ditugaskan ke proyek {judul}" |
| Hasil milestone dikirim | Klien | "Hasil milestone {nama} siap direview" |
| Revisi diminta | Developer | "Revisi #{n} diminta untuk milestone {nama}" |
| Milestone disetujui | Developer | "Milestone {nama} disetujui" |
| Proyek selesai | Klien + developer | "Proyek {judul} selesai" |

Komentar dan chat tidak mengirim email.

## 11. Tahapan

| Tahap | Isi | Selesai bila | Status |
|---|---|---|---|
| T0 | Migrasi kolom, `isPaymentConfirmed()`, penugasan developer, "Proyek Saya", kerangka Workspace + channel + halaman K3, perubahan status manual (K5) | Admin menugaskan developer; developer melihat proyeknya; workspace terkunci sebelum dibayar dan terbuka setelahnya | Selesai |
| T1 | Fitur 6+7 | Kriteria di berkas 01 lolos | Selesai |
| T2 | Fitur 1+2 | Kriteria di berkas 02 lolos | Selesai |
| T3 | Fitur 3+4 | Kriteria di berkas 03 lolos | Selesai |
| T4 | Fitur 5 | Kriteria di berkas 04 lolos | Selesai |
| T5 | Seeder data uji, regresi, README | `composer run check` hijau | Selesai |

Standar tiap tahap: Pint, Larastan, seluruh test hijau; satu test per aturan bernomor; sapuan IDOR (peserta vs bukan peserta vs admin) per endpoint baru.

## 12. Keputusan yang sudah dikonfirmasi pemilik proyek

| # | Pertanyaan | Keputusan |
|---|---|---|
| Q1 | Berapa developer per proyek? | **Satu** (K1). |
| Q2 | Siapa menyusun milestone? | **Developer**; klien hanya melihat (K6). |
| Q3 | Arti DP? | **Diganti full payment**: workspace aktif setelah proyek dibayar penuh (K2). |
| Q4 | Akses admin ke Workspace? | **Tidak ada akses admin terpisah**: admin = developer; akses mengikuti penugasan (K4). |
| Q5 | Kuota revisi? | **Sesuai kesepakatan dengan klien**, diatur per proyek (K13); bawaan 3. |
| Q6 | Persetujuan otomatis bila klien tidak merespons? | Tidak (bawaan). |

Tidak ada pertanyaan terbuka yang menghambat implementasi.
