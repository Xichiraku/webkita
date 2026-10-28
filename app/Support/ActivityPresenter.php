<?php

namespace App\Support;

use App\Enums\ProjectStatus;
use App\Models\ProjectActivity;
use InvalidArgumentException;

/**
 * Satu-satunya tempat yang mengubah (type + meta) menjadi teks dan ikon untuk riwayat aktivitas.
 * Teks tidak disimpan di database, jadi web dan email selalu konsisten.
 */
class ActivityPresenter
{
    /** Tipe aktivitas yang sah => nama ikon Lucide. */
    public const TYPES = [
        'project.created' => 'file-plus',
        'status.changed' => 'repeat',
        'price.set' => 'tag',
        'payment.paid' => 'credit-card',
        'developer.assigned' => 'user-check',
        'developer.unassigned' => 'user-minus',
        'milestone.created' => 'flag',
        'milestone.updated' => 'pencil',
        'milestone.deleted' => 'trash-2',
        'milestone.reordered' => 'arrow-up-down',
        'update.progress' => 'trending-up',
        'update.result' => 'package-check',
        'review.approved' => 'badge-check',
        'review.revision_requested' => 'rotate-ccw',
        'revision.limit_changed' => 'sliders-horizontal',
        'chat.started' => 'message-circle',
    ];

    public static function isValid(string $type): bool
    {
        return array_key_exists($type, self::TYPES);
    }

    /** @return list<string> */
    public static function icons(): array
    {
        return array_values(array_unique(self::TYPES));
    }

    /** Nama pelaku untuk tampilan; null = sistem. */
    public static function actorName(?string $name): string
    {
        return $name ?: 'Sistem';
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public static function text(string $type, array $meta, ?string $actor): string
    {
        $who = self::actorName($actor);
        $q = fn (string $key) => '“'.($meta[$key] ?? '').'”';

        return match ($type) {
            'project.created' => "{$who} mengajukan proyek".(! empty($meta['plan']) ? " (paket {$meta['plan']})" : '').'.',
            'status.changed' => 'Status berubah: '.self::status($meta['from'] ?? null).' → '.self::status($meta['to'] ?? null).'.'
                .(! empty($meta['reason']) ? " Alasan: {$meta['reason']}" : '')
                .(! empty($meta['backfilled']) ? ' (riwayat awal tidak tersedia)' : ''),
            'price.set' => 'Harga final ditetapkan '.Ui::rp($meta['amount'] ?? 0)
                .(! empty($meta['invoice_number']) ? " ({$meta['invoice_number']})" : '').'.',
            'payment.paid' => 'Pembayaran '.Ui::rp($meta['amount'] ?? 0).' diterima. Workspace aktif.',
            'developer.assigned' => ($meta['developer_name'] ?? 'Developer').' ditugaskan sebagai developer'
                .(! empty($meta['previous_name']) ? " (menggantikan {$meta['previous_name']})" : '').'.',
            'developer.unassigned' => ($meta['developer_name'] ?? 'Developer').' tidak lagi menjadi developer proyek ini.',
            'milestone.created' => "{$who} menambahkan milestone {$q('title')}.",
            'milestone.updated' => "{$who} mengubah milestone {$q('title')}.",
            'milestone.deleted' => "{$who} menghapus milestone {$q('title')}.",
            'milestone.reordered' => "{$who} mengubah urutan milestone.",
            'update.progress' => "{$who} memperbarui {$q('milestone_title')} menjadi ".(int) ($meta['percent'] ?? 0).'%.',
            'update.result' => "{$who} menyerahkan hasil {$q('milestone_title')}.",
            'review.approved' => "{$who} menyetujui {$q('milestone_title')}.",
            'review.revision_requested' => "{$who} meminta revisi #".(int) ($meta['revision_number'] ?? 0)." untuk {$q('milestone_title')}.",
            'revision.limit_changed' => 'Kuota revisi diubah '.self::limit($meta['from'] ?? null).' → '.self::limit($meta['to'] ?? null).'.',
            'chat.started' => 'Percakapan dimulai.',
            default => throw new InvalidArgumentException("Tipe aktivitas tidak dikenal: {$type}"),
        };
    }

    /** Bentuk yang dikirim API dan event realtime. */
    public static function toArray(ProjectActivity $a): array
    {
        $actor = $a->actor_id ? $a->actor : null;

        return [
            'id' => $a->id,
            'type' => $a->type,
            'actor' => $actor
                ? ['id' => $actor->id, 'name' => $actor->name, 'role' => $actor->role->value]
                : ['id' => null, 'name' => 'Sistem', 'role' => 'system'],
            'text' => self::text($a->type, $a->meta, $actor?->name),
            'icon' => self::TYPES[$a->type],
            'meta' => $a->meta,
            'created_at' => $a->created_at->toIso8601String(),
        ];
    }

    private static function status(?string $value): string
    {
        return ($value ? ProjectStatus::tryFrom($value)?->label() : null) ?? '–';
    }

    private static function limit(mixed $value): string
    {
        return $value === null ? 'tanpa batas' : (string) $value;
    }
}
