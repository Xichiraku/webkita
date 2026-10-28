<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksWorkspaceAccess;
use App\Http\Requests\SubmitUpdateRequest;
use App\Http\Resources\MilestoneResource;
use App\Http\Resources\UpdateResource;
use App\Models\ProjectMilestone;
use App\Models\ProjectUpdate;
use App\Services\MilestoneUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MilestoneUpdateController extends Controller
{
    use ChecksWorkspaceAccess;

    public function __construct(private MilestoneUpdateService $updates) {}

    /** Daftar update sebuah milestone: terbaru dulu, paginasi cursor. */
    public function index(Request $request, ProjectMilestone $milestone): JsonResponse
    {
        $this->requireParticipant($request, $milestone->project);

        $limit = min(max((int) $request->query('limit', 20), 1), 50);

        $rows = ProjectUpdate::with(['author', 'files'])
            ->where('milestone_id', $milestone->id)
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('before_id'), fn ($q, $id) => $q->where('id', '<', (int) $id))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => UpdateResource::collection($rows->take($limit)),
            'meta' => ['has_more' => $rows->count() > $limit],
        ]);
    }

    public function store(SubmitUpdateRequest $request, ProjectMilestone $milestone): JsonResponse
    {
        $project = $milestone->project;
        $this->requireDeveloper($request, $project);

        $update = $this->updates->submit($request->user(), $milestone, $request->validated(), $request->uploadedFiles());

        return response()->json([
            'status' => 'success',
            'message' => 'Update berhasil dikirim',
            'data' => new UpdateResource($update),
            'milestone' => new MilestoneResource($milestone->refresh()->loadCount('updates')),
            'project' => ['status' => $project->refresh()->status->value, 'progress_percent' => $project->progress_percent],
        ], 201);
    }
}
