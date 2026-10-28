<?php

namespace App\Support;

class Price
{
    /** Batas harga final proyek yang masuk akal (Rp 10.000 - Rp 10 miliar). */
    public const MIN_PROJECT = 10_000;

    public const MAX_PROJECT = 10_000_000_000;

    /**
     * Ubah teks harga ("9000000", "Rp 9.000.000") menjadi bilangan bulat rupiah.
     * Mengembalikan 0 bila tidak ada angka yang masuk akal.
     */
    public static function parse(mixed $value): int
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) && $value > 0 ? (int) round($value) : 0;
        }

        if (! is_string($value)) {
            return 0;
        }

        $stripped = preg_replace('/[.,\s]/', '', $value);

        if (! preg_match('/\d+/', $stripped, $m)) {
            return 0;
        }

        $n = (int) $m[0];

        return $n > 0 && strlen(ltrim($m[0], '0')) < 19 ? $n : 0;
    }
}
