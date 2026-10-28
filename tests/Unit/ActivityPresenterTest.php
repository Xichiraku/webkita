<?php

namespace Tests\Unit;

use App\Support\ActivityPresenter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActivityPresenterTest extends TestCase
{
    #[DataProvider('texts')]
    public function test_teks_per_tipe(string $type, array $meta, ?string $actor, string $expected): void
    {
        $this->assertSame($expected, ActivityPresenter::text($type, $meta, $actor));
    }

    public static function texts(): array
    {
        return [
            'proyek dibuat' => ['project.created', ['plan' => 'Starter'], 'Budi', 'Budi mengajukan proyek (paket Starter).'],
            'proyek dibuat tanpa paket' => ['project.created', ['plan' => null], 'Budi', 'Budi mengajukan proyek.'],
            'status' => ['status.changed', ['from' => 'pending', 'to' => 'approved', 'reason' => null], 'Admin', 'Status berubah: Menunggu → Disetujui.'],
            'status ditolak + alasan' => ['status.changed', ['from' => 'pending', 'to' => 'rejected', 'reason' => 'Di luar cakupan'], 'Admin', 'Status berubah: Menunggu → Ditolak. Alasan: Di luar cakupan'],
            'status backfill' => ['status.changed', ['from' => 'pending', 'to' => 'process', 'backfilled' => true], null, 'Status berubah: Menunggu → Dalam Proses. (riwayat awal tidak tersedia)'],
            'harga' => ['price.set', ['amount' => 3000000, 'invoice_number' => 'INV-0012-2026'], 'Admin', 'Harga final ditetapkan Rp 3.000.000 (INV-0012-2026).'],
            'bayar' => ['payment.paid', ['amount' => 3000000], null, 'Pembayaran Rp 3.000.000 diterima. Workspace aktif.'],
            'developer' => ['developer.assigned', ['developer_name' => 'Sari', 'previous_name' => null], 'Admin', 'Sari ditugaskan sebagai developer.'],
            'developer ganti' => ['developer.assigned', ['developer_name' => 'Sari', 'previous_name' => 'Joko'], 'Admin', 'Sari ditugaskan sebagai developer (menggantikan Joko).'],
            'developer lepas' => ['developer.unassigned', ['developer_name' => 'Sari'], 'Admin', 'Sari tidak lagi menjadi developer proyek ini.'],
            'milestone baru' => ['milestone.created', ['title' => 'Desain'], 'Sari', 'Sari menambahkan milestone “Desain”.'],
            'milestone ubah' => ['milestone.updated', ['title' => 'Desain'], 'Sari', 'Sari mengubah milestone “Desain”.'],
            'milestone hapus' => ['milestone.deleted', ['title' => 'Desain'], 'Sari', 'Sari menghapus milestone “Desain”.'],
            'milestone urut' => ['milestone.reordered', [], 'Sari', 'Sari mengubah urutan milestone.'],
            'progres' => ['update.progress', ['milestone_title' => 'Desain', 'percent' => 40], 'Sari', 'Sari memperbarui “Desain” menjadi 40%.'],
            'hasil' => ['update.result', ['milestone_title' => 'Desain'], 'Sari', 'Sari menyerahkan hasil “Desain”.'],
            'setuju' => ['review.approved', ['milestone_title' => 'Desain'], 'Budi', 'Budi menyetujui “Desain”.'],
            'revisi' => ['review.revision_requested', ['milestone_title' => 'Desain', 'revision_number' => 1], 'Budi', 'Budi meminta revisi #1 untuk “Desain”.'],
            'kuota' => ['revision.limit_changed', ['from' => 3, 'to' => 5], 'Sari', 'Kuota revisi diubah 3 → 5.'],
            'kuota tanpa batas' => ['revision.limit_changed', ['from' => 3, 'to' => null], 'Sari', 'Kuota revisi diubah 3 → tanpa batas.'],
            'chat' => ['chat.started', [], null, 'Percakapan dimulai.'],
            'pelaku sistem' => ['update.result', ['milestone_title' => 'X'], null, 'Sistem menyerahkan hasil “X”.'],
        ];
    }

    public function test_setiap_tipe_punya_ikon_dan_teks(): void
    {
        foreach (ActivityPresenter::TYPES as $type => $icon) {
            $this->assertNotSame('', $icon, $type);
            $this->assertNotSame('', ActivityPresenter::text($type, [], 'Seseorang'), $type);
        }
        $this->assertContains('repeat', ActivityPresenter::icons());
        $this->assertSame(count(array_unique(ActivityPresenter::TYPES)), count(ActivityPresenter::icons()));
    }

    public function test_tipe_tidak_dikenal_ditolak(): void
    {
        $this->assertFalse(ActivityPresenter::isValid('bukan.tipe'));
        $this->expectException(InvalidArgumentException::class);
        ActivityPresenter::text('bukan.tipe', [], null);
    }
}
