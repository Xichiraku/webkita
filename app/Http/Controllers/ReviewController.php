<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksWorkspaceAccess;
use App\Http\Controllers\Concerns\RespondsWithJson;
use App\Http\Resources\MilestoneResource;
use App\Http\Resources\RevisionResource;
use App\Http\Resources\UpdateResource;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Peninjauan hasil oleh klien, daftar revisi, dan kuota revisi. */
class ReviewController extends Controller
{
    use ChecksWorkspaceAccess, RespondsWithJson;

    public function __construct(private ReviewService $reviews) {}

    public function approve(Request $request, ProjectUpdate $update): JsonResponse
    {
        $this->requireClient($request, $update->project);

        $this->reviews->approve($request->user(), $update);

        return $this->ok($this->payload($update), 'Hasil disetujui');
    }

    public function requestRevision(Request $request, ProjectUpdate $update): JsonResponse
    {
        $this->requireClient($request, $update->project);

        $data = $request->validate(['reason' => ['required', 'string', 'min:20', 'max:2000']], [
            'reason.required' => 'Jelaskan bagian yang perlu direvisi (minimal 20 karakter).',
            'reason.min' => 'Jelaskan bagian yang perlu direvisi (minimal 20 karakter).',
            'reason.max' => 'Alasan revisi maksimal 2000 karakter.',
        ]);

        $revision = $this->reviews->requestRevision($request->user(), $update, trim($data['reason']));

        return $this->ok($this->payload($update) + ['revision' => (new RevisionResource($revision))->resolve($request)], 'Revisi diminta');
    }

    public function revisions(Request $request, Project $project): JsonResponse
    {
        $this->requireParticipant($request, $project);

        return $this->ok(RevisionResource::collection($project->revisions()->with('milestone')->get())->resolve($request));
    }

    public function setLimit(Request $request, Project $project): JsonResponse
    {
        $this->requireDeveloper($request, $project);

        $data = $request->validate(['limit' => ['present', 'nullable', 'integer', 'between:0,50']], [
            'limit.present' => 'Kuota wajib diisi (0–50) atau null untuk tanpa batas.',
            'limit.integer' => 'Kuota harus berupa bilangan bulat.',
            'limit.between' => 'Kuota harus antara 0 dan 50.',
        ]);

        $this->reviews->setRevisionLimit($request->user(), $project, $data['limit']);

        return $this->ok(['revisions_used' => $project->revisions_used, 'revision_limit' => $project->revision_limit], 'Kuota revisi diperbarui');
    }

    /** @return array{update: array<string, mixed>, milestone: array<string, mixed>, project: array<string, mixed>} */
    private function payload(ProjectUpdate $update): array
    {
        $update->refresh()->load(['author', 'files']);
        $project = Project::findOrFail($update->project_id);

        return [
            'update' => (new UpdateResource($update))->resolve(),
            'milestone' => (new MilestoneResource($update->milestone->loadCount('updates')))->resolve(),
            'project' => [
                'status' => $project->status->value,
                'progress_percent' => $project->progress_percent,
                'revisions_used' => $project->revisions_used,
                'revision_limit' => $project->revision_limit,
            ],
        ];
    }
}
