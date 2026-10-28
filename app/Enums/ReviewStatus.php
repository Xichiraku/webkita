<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case RevisionRequested = 'revision_requested';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu review',
            self::Approved => 'Disetujui',
            self::RevisionRequested => 'Revisi diminta',
        };
    }
}
