<?php

namespace App\Enums;

enum MilestoneStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Revision = 'revision';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Direncanakan',
            self::InProgress => 'Dikerjakan',
            self::Submitted => 'Menunggu review',
            self::Revision => 'Revisi diminta',
            self::Approved => 'Disetujui',
        };
    }

    public function chip(): string
    {
        return match ($this) {
            self::Planned => 'bg-ink-100 text-ink-700',
            self::InProgress => 'bg-[#CFFAFE] text-[#0E7490]',
            self::Submitted => 'bg-[#FEF3C7] text-[#B45309]',
            self::Revision => 'bg-[#FEE2E2] text-[#B91C1C]',
            self::Approved => 'bg-[#DCFCE7] text-[#15803D]',
        };
    }
}
