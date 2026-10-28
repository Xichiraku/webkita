<?php

namespace Tests\Unit;

use App\Enums\ProjectStatus;
use App\Support\Price;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DomainRulesTest extends TestCase
{
    #[DataProvider('prices')]
    public function test_parse_harga(mixed $input, int $expected): void
    {
        $this->assertSame($expected, Price::parse($input));
    }

    public static function prices(): array
    {
        return [
            'angka teks' => ['9000000', 9000000],
            'format rupiah' => ['Rp 9.000.000', 9000000],
            'koma ribuan' => ['1,500,000', 1500000],
            'integer' => [750000, 750000],
            'float' => [750000.4, 750000],
            'nol' => ['0', 0],
            'negatif' => [-5, 0],
            'bukan angka' => ['abc', 0],
            'null' => [null, 0],
            'array' => [[1], 0],
            'terlalu besar' => [str_repeat('9', 30), 0],
        ];
    }

    public function test_alur_status_manual_admin(): void
    {
        $this->assertSame([ProjectStatus::Approved, ProjectStatus::Rejected], ProjectStatus::Pending->allowedNext());
        $this->assertSame([ProjectStatus::Rejected], ProjectStatus::Approved->allowedNext());
        $this->assertFalse(ProjectStatus::Approved->canBecome(ProjectStatus::Process), 'approved -> process hanya oleh sistem');
        $this->assertFalse(ProjectStatus::Process->canBecome(ProjectStatus::Done), 'process -> done hanya oleh sistem');
        $this->assertSame([], ProjectStatus::Process->allowedNext());
        $this->assertSame([], ProjectStatus::Done->allowedNext());
        $this->assertSame([], ProjectStatus::Rejected->allowedNext(), 'Ditolak bersifat final');
    }

    public function test_alur_status_oleh_sistem(): void
    {
        $this->assertTrue(ProjectStatus::Approved->canBecomeBySystem(ProjectStatus::Process));
        $this->assertTrue(ProjectStatus::Process->canBecomeBySystem(ProjectStatus::Done));
        $this->assertFalse(ProjectStatus::Pending->canBecomeBySystem(ProjectStatus::Process));
        $this->assertFalse(ProjectStatus::Process->canBecomeBySystem(ProjectStatus::Rejected));
        $this->assertSame([], ProjectStatus::Done->systemNext());
        $this->assertSame([], ProjectStatus::Rejected->systemNext());
    }

    public function test_label_status(): void
    {
        $this->assertSame('Dalam Proses', ProjectStatus::Process->label());
        $this->assertSame('Diproses', ProjectStatus::Process->adminLabel());
        $this->assertSame('Menunggu', ProjectStatus::Pending->adminLabel());
    }
}
