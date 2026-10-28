<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RespondsWithJson;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use App\Models\Project;
use App\Policies\ProjectWorkspacePolicy;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ChatController extends Controller
{
    use RespondsWithJson;

    public function __construct(private ChatService $chat) {}

    /** Terbaru dulu; paginasi cursor. Dengan `after_id`: pesan yang terlewat, naik (pemulihan koneksi). */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->requireChat($request, $project);

        if ($request->filled('after_id')) {
            $rows = $this->chat->after($project, (int) $request->query('after_id'));

            return $this->ok(ChatMessageResource::collection($rows)->resolve($request));
        }

        $page = $this->chat->page($request->user(), $project, min(max((int) $request->query('limit', 30), 1), 50), $request->filled('before_id') ? (int) $request->query('before_id') : null);

        return response()->json([
            'status' => 'success',
            'data' => ChatMessageResource::collection($page['data'])->resolve($request),
            'meta' => ['has_more' => $page['has_more'], 'last_read_message_id' => $page['last_read_message_id']],
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->requireChat($request, $project);

        $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'files' => ['nullable', 'array', 'max:'.ChatService::MAX_FILES],
            'files.*' => ['file', 'mimetypes:'.implode(',', ChatService::MIMES), 'max:'.ChatService::MAX_FILE_KB],
        ], [
            'body.max' => 'Pesan maksimal 2000 karakter.',
            'files.max' => 'Maksimal 3 lampiran, masing-masing 10 MB (PNG, JPG, atau PDF).',
            'files.*.mimetypes' => 'Maksimal 3 lampiran, masing-masing 10 MB (PNG, JPG, atau PDF).',
            'files.*.max' => 'Maksimal 3 lampiran, masing-masing 10 MB (PNG, JPG, atau PDF).',
            'files.*.file' => 'Maksimal 3 lampiran, masing-masing 10 MB (PNG, JPG, atau PDF).',
        ]);

        $files = array_values(array_filter((array) $request->file('files'), fn ($f) => $f instanceof UploadedFile));
        $body = trim((string) $request->input('body'));

        if ($body === '' && ! $files) {
            return response()->json(['status' => 'error', 'message' => 'Tulis pesan atau lampirkan berkas.', 'errors' => ['body' => ['Tulis pesan atau lampirkan berkas.']]], 422);
        }

        $message = $this->chat->send($request->user(), $project, $body, $files);

        return $this->ok((new ChatMessageResource($message))->resolve($request), 'Pesan terkirim', 201);
    }

    public function destroy(Request $request, ChatMessage $message): JsonResponse
    {
        $this->requireChat($request, $message->project);

        $this->chat->delete($request->user(), $message);

        return $this->ok(null, 'Pesan dihapus');
    }

    public function read(Request $request, Project $project): JsonResponse
    {
        $this->requireChat($request, $project);

        $data = $request->validate(['last_message_id' => ['required', 'integer', 'min:0']]);

        return $this->ok(['unread' => $this->chat->markRead($request->user(), $project, (int) $data['last_message_id'])]);
    }

    /** Belum dibaca per proyek untuk pengguna yang login. */
    public function unread(Request $request): JsonResponse
    {
        return $this->ok((object) $this->chat->unreadMap($request->user()));
    }

    /** R5.2–R5.3: peserta, pembayaran terkonfirmasi, dan developer sudah ditugaskan. */
    private function requireChat(Request $request, Project $project): void
    {
        abort_unless(app(ProjectWorkspacePolicy::class)->participate($request->user(), $project), 403, 'Anda bukan peserta percakapan ini.');
        abort_unless($project->isPaymentConfirmed(), 409, 'Workspace aktif setelah pembayaran terkonfirmasi.');
        abort_if($project->developer_id === null, 409, 'Chat tersedia setelah developer ditugaskan.');
    }
}
