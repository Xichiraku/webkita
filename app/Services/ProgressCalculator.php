<?php

namespace App\Services;

use App\Enums\MilestoneStatus;
use App\Models\Project;

/**
 * Persentase proyek = rata-rata bulat persentase semua milestone (bobot sama).
 * Milestone `planned` dihitung 0 dan `approved` 100 (nilai kolom `percent` sudah mencerminkannya).
 */
class ProgressCalculator
{
    public function calculate(Project $project): int
    {
        $milestones = $project->milestones()->get(['status', 'percent']);

        if ($milestones->isEmpty()) {
            return 0;
        }

        $sum = $milestones->sum(fn ($m) => match ($m->status) {
            MilestoneStatus::Planned => 0,
            MilestoneStatus::Approved => 100,
            default => $m->percent,
        });

        return (int) round($sum / $milestones->count());
    }

    /** Hitung ulang dan simpan ke `projects.progress_percent` (panggil di dalam transaksi pemicunya). */
    public function recalculate(Project $project): int
    {
        $percent = $this->calculate($project);

        Project::whereKey($project->id)->update(['progress_percent' => $percent]);
        $project->progress_percent = $percent;

        return $percent;
    }
}
