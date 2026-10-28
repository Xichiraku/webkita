<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsWithJson;
use App\Models\ChatMessage;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    use RespondsWithJson;

    public function __construct(private AttachmentService $attachments) {}

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $request->validate([
            'file' => ['required', 'file', 'mimetypes:'.implode(',', AttachmentService::MIMES), 'max:'.AttachmentService::MAX_KB],
        ], [
            'file.required' => 'File tidak ditemukan',
            'file.mimetypes' => 'Tipe file harus PDF, PNG, atau JPG',
            'file.max' => 'File maksimal 10MB',
        ]);

        return $this->ok($this->attachments->upload($project, $request->file('file')), 'File berhasil diupload', 201);
    }

    public function index(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return $this->ok($this->attachments->list($project));
    }

    public function download(ProjectAttachment $attachment): StreamedResponse
    {
        // Berkas update workspace hanya untuk peserta (klien pemilik / developer ditugaskan), bukan staf lain.
        // Lampiran chat: peserta saja, dan tidak lagi dapat diunduh bila pesannya dihapus (R5.7).
        if ($attachment->message_id !== null) {
            $this->authorize('workspace.access', $attachment->project);
            abort_if(ChatMessage::whereKey($attachment->message_id)->doesntExist(), 404);
        } else {
            $attachment->update_id !== null
                ? $this->authorize('workspace.access', $attachment->project)
                : $this->authorize('view', $attachment->project);
        }

        return $this->attachments->download($attachment);
    }
}
