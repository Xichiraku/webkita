<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectAttachment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentService
{
    public const DISK = 'local'; // privat: file hanya bisa diunduh lewat controller terotorisasi

    public const MAX_KB = 10240; // 10 MB

    public const MIMES = ['application/pdf', 'image/png', 'image/jpeg'];

    public function upload(Project $project, UploadedFile $file): ProjectAttachment
    {
        // Tipe & ukuran diambil dari isi file di server, bukan dari klaim klien.
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $path = $file->store("attachments/{$project->id}", self::DISK);

        return $project->attachments()->create([
            'file_name' => basename($file->getClientOriginalName()),
            'file_size' => $file->getSize(),
            'file_type' => $mime,
            'storage_path' => $path,
        ]);
    }

    /** @return Collection<int, ProjectAttachment> */
    public function list(Project $project): Collection
    {
        return $project->attachments()->whereNull('update_id')->whereNull('message_id')->latest('created_at')->latest('id')->get();
    }

    public function download(ProjectAttachment $attachment): StreamedResponse
    {
        return Storage::disk(self::DISK)->download($attachment->storage_path, $attachment->file_name, [
            'Content-Type' => $attachment->file_type,
        ]);
    }
}
