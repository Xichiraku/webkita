<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksWorkspaceAccess;
use App\Http\Controllers\Concerns\RespondsWithJson;
use App\Http\Resources\CommentResource;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    use ChecksWorkspaceAccess, RespondsWithJson;

    public function __construct(private CommentService $comments) {}

    /** Terbaru dulu; paginasi cursor. */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->requireParticipant($request, $project);

        $limit = min(max((int) $request->query('limit', 30), 1), 50);
        $page = $this->comments->page(
            $project, $limit,
            $request->filled('before_id') ? (int) $request->query('before_id') : null,
            $request->filled('update_id') ? (int) $request->query('update_id') : null,
        );

        return response()->json([
            'status' => 'success',
            'data' => CommentResource::collection($page['data'])->resolve($request),
            'meta' => ['has_more' => $page['has_more']],
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->requireParticipant($request, $project);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000', 'regex:/\S/u'],
            'update_id' => ['nullable', 'integer'],
        ], [
            'body.required' => 'Komentar wajib diisi (1–2000 karakter).',
            'body.max' => 'Komentar maksimal 2000 karakter.',
            'body.regex' => 'Komentar wajib diisi (1–2000 karakter).',
        ]);

        $comment = $this->comments->post($project, $request->user(), $data['body'], $data['update_id'] ?? null);

        return $this->ok((new CommentResource($comment))->resolve($request), 'Komentar terkirim', 201);
    }

    public function destroy(Request $request, ProjectComment $comment): JsonResponse
    {
        // Hanya peserta proyek yang boleh menyentuh komentar; pemilik dan batas waktu dicek di service.
        $this->requireParticipant($request, $comment->project);

        $this->comments->delete($request->user(), $comment);

        return $this->ok(null, 'Komentar dihapus');
    }
}
