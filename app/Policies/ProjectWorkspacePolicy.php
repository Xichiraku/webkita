<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Aturan akses Project Workspace. Peserta hanya klien pemilik dan developer yang ditugaskan;
 * peran admin tidak memberi akses (admin = developer, akses mengikuti penugasan).
 * Dipakai oleh controller, API, dan otorisasi channel `project.{id}`.
 */
class ProjectWorkspacePolicy
{
    public function isClient(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }

    public function isDeveloper(User $user, Project $project): bool
    {
        return $project->developer_id !== null && $project->developer_id === $user->id;
    }

    /** Klien pemilik atau developer yang ditugaskan (tanpa memeriksa pembayaran). */
    public function participate(User $user, Project $project): bool
    {
        return $this->isClient($user, $project) || $this->isDeveloper($user, $project);
    }

    /** Peserta DAN workspace aktif (pembayaran terkonfirmasi): syarat semua fitur workspace. */
    public function access(User $user, Project $project): bool
    {
        return $this->participate($user, $project) && $project->isPaymentConfirmed();
    }
}
