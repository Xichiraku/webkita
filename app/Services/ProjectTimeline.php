<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Support\ActivityPresenter;

/**
 * Halaman riwayat aktivitas satu proyek (dipakai API dan render awal Workspace).
 */
class ProjectTimeline
{
    public const DEFAULT_LIMIT = 30;

    public const MAX_LIMIT = 50;

    /**
     * Aktivitas terbaru dulu, paginasi cursor berbasis id.
     *
     * @return array{data: list<array<string, mixed>>, has_more: bool}
     */
    public function page(Project $project, ?int $beforeId = null, int $limit = self::DEFAULT_LIMIT): array
    {
        $limit = min(max($limit, 1), self::MAX_LIMIT);

        $rows = ProjectActivity::with('actor')
            ->where('project_id', $project->id)
            ->when($beforeId, fn ($q, $id) => $q->where('id', '<', $id))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        return [
            'data' => $rows->take($limit)->map(fn (ProjectActivity $a) => ActivityPresenter::toArray($a))->values()->all(),
            'has_more' => $rows->count() > $limit,
        ];
    }
}
