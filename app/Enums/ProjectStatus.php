<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Process = 'process';
    case Done = 'done';
    case Rejected = 'rejected';

    /**
     * Perpindahan yang boleh dipilih **admin secara manual**.
     * `approved -> process` dan `process -> done` hanya dilakukan sistem (lihat systemNext()).
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Rejected],
            self::Approved => [self::Rejected],
            self::Process, self::Done, self::Rejected => [],
        };
    }

    /**
     * Perpindahan yang hanya boleh dilakukan **sistem** (update pertama developer, persetujuan milestone terakhir).
     *
     * @return list<self>
     */
    public function systemNext(): array
    {
        return match ($this) {
            self::Approved => [self::Process],
            self::Process => [self::Done],
            default => [],
        };
    }

    public function canBecome(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    public function canBecomeBySystem(self $next): bool
    {
        return in_array($next, $this->systemNext(), true);
    }

    /** Label untuk klien. */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Process => 'Dalam Proses',
            self::Done => 'Selesai',
            self::Rejected => 'Ditolak',
        };
    }

    /** Label untuk panel admin. */
    public function adminLabel(): string
    {
        return $this === self::Process ? 'Diproses' : $this->label();
    }

    public function chip(): string
    {
        return match ($this) {
            self::Pending => 'bg-[#FEF3C7] text-[#B45309]',
            self::Approved => 'bg-brand-100 text-brand-800',
            self::Process => 'bg-[#CFFAFE] text-[#0E7490]',
            self::Done => 'bg-[#DCFCE7] text-[#15803D]',
            self::Rejected => 'bg-[#FEE2E2] text-[#B91C1C]',
        };
    }

    public function dot(): string
    {
        return match ($this) {
            self::Pending => '#D97706',
            self::Approved => '#2559CC',
            self::Process => '#0E7490',
            self::Done => '#15803D',
            self::Rejected => '#DC2626',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'lucide-clock-3',
            self::Approved => 'lucide-badge-check',
            self::Process => 'lucide-trending-up',
            self::Done => 'lucide-circle-check',
            self::Rejected => 'lucide-circle-x',
        };
    }
}
