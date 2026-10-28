<?php

namespace App\Http\Requests;

use App\Models\ProjectUpdate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class SubmitUpdateRequest extends FormRequest
{
    public const MAX_FILES = 10;

    public const MAX_FILE_KB = 25 * 1024;

    public const MAX_TOTAL_BYTES = 100 * 1024 * 1024;

    public const MAX_LINKS = 5;

    public const MIMES = ['application/pdf', 'image/png', 'image/jpeg', 'application/zip', 'application/x-zip-compressed'];

    public function authorize(): bool
    {
        return true; // akses dicek di controller (developer proyek)
    }

    public function rules(): array
    {
        $isResult = $this->input('type') === ProjectUpdate::TYPE_RESULT;

        return [
            'type' => ['required', Rule::in([ProjectUpdate::TYPE_PROGRESS, ProjectUpdate::TYPE_RESULT])],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
            'percent' => $isResult ? ['nullable'] : ['required', 'integer', 'between:0,99'],
            'title' => $isResult ? ['required', 'string', 'min:3', 'max:100'] : ['nullable'],
            'links' => ['nullable', 'array', 'max:'.self::MAX_LINKS],
            'links.*' => ['required', 'string', 'max:500', 'url:https'],
            'files' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'files.*' => ['file', 'mimetypes:'.implode(',', self::MIMES), 'max:'.self::MAX_FILE_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'type.*' => 'Jenis update harus progres atau hasil.',
            'note.required' => 'Catatan wajib diisi (10–2000 karakter).',
            'note.min' => 'Catatan minimal 10 karakter.',
            'note.max' => 'Catatan maksimal 2000 karakter.',
            'percent.required' => 'Persentase wajib diisi (0–99).',
            'percent.integer' => 'Persentase harus berupa bilangan bulat.',
            'percent.between' => 'Persentase harus antara 0 dan 99. Gunakan "hasil" untuk menyerahkan milestone.',
            'title.required' => 'Judul hasil wajib diisi (3–100 karakter).',
            'title.min' => 'Judul hasil minimal 3 karakter.',
            'links.max' => 'Maksimal '.self::MAX_LINKS.' tautan.',
            'links.*.url' => 'Tautan harus diawali https://',
            'links.*.max' => 'Tautan maksimal 500 karakter.',
            'files.max' => 'Maksimal '.self::MAX_FILES.' berkas.',
            'files.*.mimetypes' => 'Tipe berkas harus PDF, PNG, JPG, atau ZIP.',
            'files.*.max' => 'Setiap berkas maksimal 25 MB.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $files = $this->uploadedFiles();
            $total = array_sum(array_map(fn (UploadedFile $f) => $f->getSize(), $files));

            if ($total > self::MAX_TOTAL_BYTES) {
                $validator->errors()->add('files', 'Total ukuran berkas maksimal 100 MB.');
            }

            if ($this->input('type') === ProjectUpdate::TYPE_RESULT && ! $files && ! array_filter((array) $this->input('links'))) {
                $validator->errors()->add('files', 'Sertakan minimal satu berkas atau tautan hasil pengerjaan.');
            }
        }];
    }

    /** @return list<UploadedFile> */
    public function uploadedFiles(): array
    {
        return array_values(array_filter((array) $this->file('files'), fn ($f) => $f instanceof UploadedFile));
    }
}
