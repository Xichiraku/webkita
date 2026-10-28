<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Challenge = 'challenge';
    case Paid = 'paid';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Paid => 'Lunas',
            self::Failed => 'Gagal',
            self::Challenge => 'Challenge',
        };
    }

    public function note(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pembayaran dari klien',
            self::Paid => 'Pembayaran diterima',
            self::Failed => 'Pembayaran ditolak, dibatalkan, atau kedaluwarsa',
            self::Challenge => 'Ditahan untuk tinjauan risiko penipuan',
        };
    }

    public function chip(): string
    {
        return match ($this) {
            self::Pending => 'bg-[#FEF3C7] text-[#B45309]',
            self::Paid => 'bg-[#DCFCE7] text-[#15803D]',
            self::Failed => 'bg-[#FEE2E2] text-[#B91C1C]',
            self::Challenge => 'bg-[#E0E7FF] text-[#3730A3]',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'lucide-clock-3',
            self::Paid => 'lucide-circle-check',
            self::Failed => 'lucide-circle-x',
            self::Challenge => 'lucide-shield-alert',
        };
    }
}
