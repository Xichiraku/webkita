<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validasi milestone. Pembuatan: wajib judul + tenggat tidak boleh lampau; ubah: field opsional. */
class MilestoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // akses dicek di controller (developer proyek)
    }

    public function rules(): array
    {
        $creating = $this->isMethod('POST');

        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'min:3', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'due_date' => ['nullable', 'date', ...($creating ? ['after_or_equal:today'] : [])],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Judul milestone wajib diisi.',
            'title.min' => 'Judul milestone minimal 3 karakter.',
            'title.max' => 'Judul milestone maksimal 100 karakter.',
            'description.max' => 'Deskripsi maksimal 1000 karakter.',
            'due_date.date' => 'Tenggat harus berupa tanggal yang valid.',
            'due_date.after_or_equal' => 'Tenggat tidak boleh di masa lalu.',
        ];
    }
}
