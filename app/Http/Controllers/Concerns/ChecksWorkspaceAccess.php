<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Policies\ProjectWorkspacePolicy;
use Illuminate\Http\Request;

trait ChecksWorkspaceAccess
{
    /** Klien pemilik atau developer ditugaskan, dan workspace sudah aktif (pembayaran terkonfirmasi). */
    protected function requireParticipant(Request $request, Project $project): void
    {
        abort_unless($this->workspacePolicy()->participate($request->user(), $project), 403, 'Anda bukan peserta proyek ini.');
        abort_unless($project->isPaymentConfirmed(), 409, 'Workspace aktif setelah pembayaran terkonfirmasi.');
    }

    /** Hanya developer yang ditugaskan pada proyek ini. */
    protected function requireDeveloper(Request $request, Project $project): void
    {
        $policy = $this->workspacePolicy();

        abort_if($policy->isClient($request->user(), $project), 403, 'Hanya developer proyek yang dapat melakukan ini.');
        abort_unless($policy->isDeveloper($request->user(), $project), 403, 'Anda tidak ditugaskan pada proyek ini.');
    }

    /** Hanya klien pemilik proyek (peninjauan hasil, R3.7). Workspace harus aktif. */
    protected function requireClient(Request $request, Project $project): void
    {
        abort_unless($this->workspacePolicy()->isClient($request->user(), $project), 403, 'Hanya pemilik proyek yang dapat meninjau hasil.');
        abort_unless($project->isPaymentConfirmed(), 409, 'Workspace aktif setelah pembayaran terkonfirmasi.');
    }

    private function workspacePolicy(): ProjectWorkspacePolicy
    {
        return app(ProjectWorkspacePolicy::class);
    }
}
