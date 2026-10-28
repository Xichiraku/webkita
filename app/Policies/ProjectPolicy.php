<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Aturan akses dari backend lama: pemilik atau staf (admin/developer) boleh melihat;
 * hanya admin yang boleh mengubah status, menetapkan harga, dan menghapus.
 * Attachment mengikuti project induknya (lihat/unggah/unduh = view).
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Project $project): bool
    {
        return $user->isStaff() || $project->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function viewUserProjects(User $user): bool
    {
        return $user->isAdmin();
    }

    public function updateStatus(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function assignDeveloper(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function setPrice(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }
}
