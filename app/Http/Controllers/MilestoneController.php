<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksWorkspaceAccess;
use App\Http\Controllers\Concerns\RespondsWithJson;
use App\Http\Requests\MilestoneRequest;
use App\Http\Resources\MilestoneResource;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Services\MilestoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    use ChecksWorkspaceAccess, RespondsWithJson;

    public function __construct(private MilestoneService $milestones) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->requireParticipant($request, $project);

        return $this->ok(MilestoneResource::collection($this->milestones->list($project)));
    }

    public function store(MilestoneRequest $request, Project $project): JsonResponse
    {
        $this->requireDeveloper($request, $project);

        $milestone = $this->milestones->create($project, $request->validated(), $request->user());

        return $this->ok(new MilestoneResource($milestone), 'Milestone ditambahkan', 201);
    }

    public function update(MilestoneRequest $request, ProjectMilestone $milestone): JsonResponse
    {
        $this->requireDeveloper($request, $milestone->project);

        return $this->ok(new MilestoneResource($this->milestones->update($milestone, $request->validated(), $request->user())), 'Milestone diperbarui');
    }

    public function destroy(Request $request, ProjectMilestone $milestone): JsonResponse
    {
        $this->requireDeveloper($request, $milestone->project);
        $this->milestones->delete($milestone, $request->user());

        return $this->ok(null, 'Milestone dihapus');
    }

    public function reorder(Request $request, Project $project): JsonResponse
    {
        $this->requireDeveloper($request, $project);

        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']], [
            'ids.*' => 'Daftar urutan tidak valid.',
        ]);

        $this->milestones->reorder($project, $data['ids'], $request->user());

        return $this->ok(MilestoneResource::collection($this->milestones->list($project)), 'Urutan milestone diperbarui');
    }
}
