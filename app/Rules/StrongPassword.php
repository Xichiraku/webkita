<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Aturan password dari backend lama: 8-72 byte (batas bcrypt), mengandung huruf dan angka.
 */
class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Password wajib diisi');
        } elseif (strlen($value) < 8) {
            $fail('Password minimal 8 karakter');
        } elseif (strlen($value) > 72) {
            $fail('Password maksimal 72 karakter');
        } elseif (! preg_match('/[A-Za-z]/', $value) || ! preg_match('/\d/', $value)) {
            $fail('Password harus mengandung huruf dan angka');
        }
    }
}
