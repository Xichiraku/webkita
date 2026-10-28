<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Concerns\RespondsWithJson;
use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    use RespondsWithJson;

    public function __construct(private ProjectService $projects) {}

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        return $this->ok($this->projects->create($request->user(), $request->validated()), 'Project berhasil dibuat', 201);
    }

    public function my(Request $request): JsonResponse
    {
        return $this->ok($this->projects->forUser($request->user()->id), 'Project berhasil diambil');
    }

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        return $this->ok($this->projects->all(), 'Semua project berhasil diambil');
    }

    public function byUser(int $userId): JsonResponse
    {
        $this->authorize('viewUserProjects', Project::class);

        return $this->ok($this->projects->forUser($userId), 'Project user berhasil diambil');
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return $this->ok($project, 'Detail project berhasil diambil');
    }

    public function updateStatus(Request $request, Project $project): JsonResponse
    {
        $this->authorize('updateStatus', $project);

        $data = $request->validate([
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'reason' => ['nullable', 'string', 'max:'.ProjectService::MAX_REJECT_REASON],
        ], [
            'status.*' => 'Status project tidak valid',
            'reason.max' => 'Alasan penolakan maksimal '.ProjectService::MAX_REJECT_REASON.' karakter',
        ]);

        $updated = $this->projects->updateStatus($project, ProjectStatus::from($data['status']), $data['reason'] ?? null, $request->user());

        return $this->ok($updated, 'Status project berhasil diupdate');
    }

    public function setPrice(Request $request, Project $project): JsonResponse
    {
        $this->authorize('setPrice', $project);

        return $this->ok($this->projects->setPrice($project, $request->input('plan_price_range'), $request->user()), 'Harga berhasil diset');
    }

    public function assignDeveloper(Request $request, Project $project): JsonResponse
    {
        $this->authorize('assignDeveloper', $project);

        $data = $request->validate(['developer_id' => ['present', 'nullable', 'integer']], [
            'developer_id.*' => 'Developer tidak valid',
        ]);

        $updated = $this->projects->assignDeveloper($project, $data['developer_id'], $request->user());

        return $this->ok($updated->load('developer:id,username,first_name,last_name'), $data['developer_id'] ? 'Developer ditugaskan' : 'Developer dilepas');
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);
        $this->projects->delete($project);

        return $this->ok(null, 'Project berhasil dihapus');
    }
}
