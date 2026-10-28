<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Kelas Tailwind yang dipakai berulang dan helper tampilan (padanan frontend/src/utils/ui.js & format.js).
 */
class Ui
{
    public const FOCUS = 'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-400';

    public const BTN_PRIMARY = 'inline-flex items-center justify-center gap-2 rounded-xl bg-brand-600 font-bold text-white shadow-soft transition hover:-translate-y-0.5 hover:bg-brand-700 hover:shadow-lift disabled:cursor-not-allowed disabled:opacity-80 disabled:hover:translate-y-0 motion-reduce:hover:translate-y-0 '.self::FOCUS;

    public const BTN_SECONDARY = 'inline-flex items-center justify-center gap-2 rounded-xl border-2 border-ink-200 bg-white font-bold text-ink-800 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700 disabled:cursor-not-allowed disabled:opacity-60 '.self::FOCUS;

    public const INPUT = 'h-12 w-full rounded-xl border border-ink-300 bg-white px-4 text-[15px] text-ink-900 placeholder:text-ink-500 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-300 aria-[invalid=true]:border-[#DC2626] aria-[invalid=true]:ring-2 aria-[invalid=true]:ring-[#FEE2E2] disabled:bg-ink-50';

    public const LABEL = 'mb-1.5 block text-sm font-bold text-ink-800';

    public const A_INPUT = 'h-11 w-full rounded-xl border border-ink-200 bg-white/80 text-sm text-ink-900 placeholder:text-ink-500 outline-none transition focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-300';

    public const A_SELECT = 'h-11 w-full cursor-pointer rounded-xl border border-ink-200 bg-white/80 px-3 text-sm font-semibold text-ink-800 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-300';

    public const A_ICON_BTN = 'inline-grid h-9 w-9 place-items-center rounded-lg border border-ink-200 bg-white/80 text-ink-700 transition hover:border-brand-400 hover:bg-brand-50 hover:text-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand-400';

    public const WA_NUMBER = '6282171484751';

    public static function waKonsultasi(): string
    {
        return 'https://wa.me/'.self::WA_NUMBER.'?text='.rawurlencode('Halo Webkita, saya mau konsultasi');
    }

    public static function waPaket(): string
    {
        return 'https://wa.me/'.self::WA_NUMBER.'?text='.rawurlencode('Halo Webkita, saya ingin konsultasi paket.');
    }

    public static function waLink(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return 'https://wa.me/'.preg_replace('/^0/', '62', $digits);
    }

    public static function rp(int|float|string|null $n): string
    {
        return 'Rp '.number_format((float) $n, 0, ',', '.');
    }

    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private const MONTHS_LONG = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public static function date(?CarbonInterface $d): string
    {
        return $d ? $d->day.' '.self::MONTHS[$d->month - 1].' '.$d->year : '–';
    }

    public static function dateLong(?CarbonInterface $d): string
    {
        return $d ? $d->day.' '.self::MONTHS_LONG[$d->month - 1].' '.$d->year : '–';
    }

    public static function initials(?string $name): string
    {
        $w = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        if (! $w) {
            return '?';
        }

        return mb_strtoupper(mb_substr($w[0], 0, 1).(count($w) > 1 ? mb_substr(end($w), 0, 1) : ''));
    }

    public static function fileSize(?int $bytes): string
    {
        if (! $bytes) {
            return '';
        }

        return $bytes < 1048576 ? max(1, (int) round($bytes / 1024)).' KB' : str_replace('.', ',', number_format($bytes / 1048576, 1)).' MB';
    }

    /** Kategori proyek: nilai => label. */
    public const CATEGORIES = [
        'website' => 'Website / Web Application',
        'mobile' => 'Mobile Application',
        'desktop' => 'Desktop Application',
        'design' => 'Design (UI/UX)',
        'marketing' => 'Marketing & Branding',
        'other' => 'Lainnya',
    ];

    public static function category(?string $v): string
    {
        return self::CATEGORIES[$v] ?? ($v ?: '–');
    }
}
