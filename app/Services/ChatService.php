<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Events\ChatMessageDeleted;
use App\Events\ChatMessageSent;
use App\Events\ChatUnreadChanged;
use App\Exceptions\ApiException;
use App\Models\ChatMessage;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Chat klien–developer satu percakapan per proyek (Fitur 5). Reverb hanya penyampai; data tetap di MySQL. */
class ChatService
{
    public const DISK = 'local';

    public const MAX_FILES = 3;

    public const MAX_FILE_KB = 10 * 1024;

    public const MIMES = ['image/png', 'image/jpeg', 'application/pdf'];

    public function __construct(private ProjectActivityLogger $activities) {}

    /**
     * Terbaru dulu; pesan terhapus ikut (isinya disembunyikan oleh resource).
     *
     * @return array{data: Collection<int, ChatMessage>, has_more: bool, last_read_message_id: int}
     */
    public function page(User $user, Project $project, int $limit, ?int $beforeId = null): array
    {
        $rows = ChatMessage::withTrashed()->with(['author', 'attachments'])
            ->where('project_id', $project->id)
            ->when($beforeId, fn ($q, $id) => $q->where('id', '<', $id))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        return [
            'data' => $rows->take($limit)->values(),
            'has_more' => $rows->count() > $limit,
            'last_read_message_id' => $this->lastRead($user, $project),
        ];
    }

    /** Pesan dengan id lebih besar dari $afterId (pemulihan koneksi), naik. */
    public function after(Project $project, int $afterId): Collection
    {
        return ChatMessage::withTrashed()->with(['author', 'attachments'])
            ->where('project_id', $project->id)->where('id', '>', $afterId)
            ->orderBy('id')->limit(200)->get();
    }

    /** @param  list<UploadedFile>  $files */
    public function send(User $sender, Project $project, ?string $body, array $files = []): ChatMessage
    {
        $body = $body !== null ? trim($body) : null;
        $body = $body === '' ? null : $body;

        $stored = [];

        try {
            $message = DB::transaction(function () use ($sender, $project, $body, $files, &$stored) {
                // Kunci proyek: status dicek ulang dan pesan pertama dicatat tepat sekali.
                $locked = Project::whereKey($project->id)->lockForUpdate()->firstOrFail();

                if (! in_array($locked->status, [ProjectStatus::Approved, ProjectStatus::Process], true)) {
                    throw new ApiException('Chat sudah ditutup. Anda masih dapat membaca riwayat.', 409);
                }

                $message = new ChatMessage;
                $message->project_id = $project->id;
                $message->user_id = $sender->id;
                $message->body = $body;
                $message->save();

                foreach ($files as $file) {
                    $path = $file->store("attachments/{$project->id}/chat/{$message->id}", self::DISK);
                    $stored[] = $path;

                    $attachment = new ProjectAttachment;
                    $attachment->project_id = $project->id;
                    $attachment->message_id = $message->id;
                    $attachment->file_name = basename($file->getClientOriginalName());
                    $attachment->file_size = (int) $file->getSize();
                    $attachment->file_type = $file->getMimeType() ?: 'application/octet-stream';
                    $attachment->storage_path = $path;
                    $attachment->uploaded_by = $sender->id;
                    $attachment->save();
                }

                if (ChatMessage::withTrashed()->where('project_id', $project->id)->count() === 1) {
                    $this->activities->log($locked, 'chat.started', [], $sender);
                }

                return $message;
            });
        } catch (Throwable $e) {
            if ($stored) {
                Storage::disk(self::DISK)->delete($stored);
            }

            throw $e;
        }

        $message->load(['author', 'attachments']);
        ChatMessageSent::dispatch($message);

        // Lencana lawan bicara bertambah.
        $recipientId = $project->user_id === $sender->id ? $project->developer_id : $project->user_id;
        if ($recipientId !== null) {
            ChatUnreadChanged::dispatch($recipientId, $project->id, $this->unread($recipientId, $project));
        }

        return $message;
    }

    public function delete(User $user, ChatMessage $message): void
    {
        if ($message->user_id !== $user->id) {
            throw new ApiException('Anda hanya dapat menghapus pesan milik sendiri.', 403);
        }

        if (! $message->canBeDeletedBy($user)) {
            throw new ApiException('Waktu menghapus pesan sudah lewat.', 403);
        }

        $message->deleted_by = $user->id;
        $message->save();
        $message->delete();

        ChatMessageDeleted::dispatch($message);

        $project = Project::findOrFail($message->project_id);
        $recipientId = $project->user_id === $user->id ? $project->developer_id : $project->user_id;
        if ($recipientId !== null) {
            ChatUnreadChanged::dispatch($recipientId, $project->id, $this->unread($recipientId, $project));
        }
    }

    /** Tandai terbaca sampai $lastId (hanya naik, dibatasi id pesan terbesar proyek). Mengembalikan sisa belum dibaca. */
    public function markRead(User $user, Project $project, int $lastId): int
    {
        $max = (int) ChatMessage::withTrashed()->where('project_id', $project->id)->max('id');
        $lastId = min($lastId, $max);

        $row = ['project_id' => $project->id, 'user_id' => $user->id];
        DB::table('chat_reads')->insertOrIgnore($row + ['last_read_message_id' => 0, 'updated_at' => now()]);
        DB::table('chat_reads')->where($row)->where('last_read_message_id', '<', $lastId)
            ->update(['last_read_message_id' => $lastId, 'updated_at' => now()]);

        $unread = $this->unread($user->id, $project);
        ChatUnreadChanged::dispatch($user->id, $project->id, $unread);

        return $unread;
    }

    public function lastRead(User $user, Project $project): int
    {
        return (int) DB::table('chat_reads')->where('project_id', $project->id)->where('user_id', $user->id)->value('last_read_message_id');
    }

    /** Pesan milik orang lain dengan id > last_read (pesan terhapus tidak dihitung). */
    public function unread(int $userId, Project $project): int
    {
        $last = (int) DB::table('chat_reads')->where('project_id', $project->id)->where('user_id', $userId)->value('last_read_message_id');

        return ChatMessage::where('project_id', $project->id)->where('user_id', '!=', $userId)->where('id', '>', $last)->count();
    }

    /**
     * Belum dibaca per proyek untuk pengguna ini; hanya proyek dengan chat aktif dan nilai > 0.
     *
     * @return array<int, int>
     */
    public function unreadMap(User $user): array
    {
        $map = [];

        Project::where(fn ($q) => $q->where('user_id', $user->id)->orWhere('developer_id', $user->id))
            ->whereNotNull('developer_id')->get()
            ->each(function (Project $p) use ($user, &$map) {
                $n = $p->isPaymentConfirmed() ? $this->unread($user->id, $p) : 0;
                if ($n > 0) {
                    $map[$p->id] = $n;
                }
            });

        return $map;
    }
}
