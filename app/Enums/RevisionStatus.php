<?php

namespace App\Enums;

enum RevisionStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Sedang dikerjakan',
            self::Resolved => 'Selesai',
        };
    }
}
