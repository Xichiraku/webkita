<?php

namespace App\Services;

use App\Events\ProjectCommentDeleted;
use App\Events\ProjectCommentPosted;
use App\Exceptions\ApiException;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Komentar pada proyek (Fitur 3). Komentar tidak dapat diedit; hapus = soft delete oleh pemilik dalam 15 menit. */
class CommentService
{
    /**
     * Terbaru dulu. Komentar terhapus ikut dikembalikan (isinya disembunyikan oleh CommentResource).
     *
     * @return array{data: Collection<int, ProjectComment>, has_more: bool}
     */
    public function page(Project $project, int $limit, ?int $beforeId = null, ?int $updateId = null): array
    {
        $rows = ProjectComment::withTrashed()->with('author')
            ->where('project_id', $project->id)
            ->when($updateId, fn ($q, $id) => $q->where('update_id', $id))
            ->when($beforeId, fn ($q, $id) => $q->where('id', '<', $id))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        return ['data' => $rows->take($limit)->values(), 'has_more' => $rows->count() > $limit];
    }

    /** Simpan tanpa menyiarkan; dipakai juga di dalam transaksi peninjauan (alasan revisi). */
    public function store(Project $project, User $author, string $body, ?int $updateId = null): ProjectComment
    {
        if ($updateId !== null && ! ProjectUpdate::where('project_id', $project->id)->whereKey($updateId)->exists()) {
            throw ValidationException::withMessages(['update_id' => 'Update tidak ditemukan pada proyek ini.']);
        }

        $comment = new ProjectComment;
        $comment->project_id = $project->id;
        $comment->update_id = $updateId;
        $comment->user_id = $author->id;
        $comment->body = trim($body);
        $comment->save();

        return $comment->setRelation('author', $author);
    }

    public function post(Project $project, User $author, string $body, ?int $updateId = null): ProjectComment
    {
        $comment = $this->store($project, $author, $body, $updateId);

        ProjectCommentPosted::dispatch($comment);

        return $comment;
    }

    public function delete(User $user, ProjectComment $comment): void
    {
        if ($comment->deleted_at !== null) {
            throw new ApiException('Komentar sudah dihapus.', 409);
        }

        if ($comment->user_id !== $user->id) {
            throw new ApiException('Anda hanya dapat menghapus komentar milik sendiri.', 403);
        }

        if (! $comment->canBeDeletedBy($user)) {
            throw new ApiException('Waktu menghapus komentar sudah lewat.', 403);
        }

        $comment->deleted_by = $user->id;
        $comment->save();
        $comment->delete();

        ProjectCommentDeleted::dispatch($comment);
    }
}
