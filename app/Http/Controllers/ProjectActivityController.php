<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectTimeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectActivityController extends Controller
{
    public function __construct(private ProjectTimeline $timeline) {}

    /** Riwayat aktivitas proyek: terbaru dulu, paginasi cursor lewat before_id. */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('workspace.participate', $project);

        abort_unless($project->isPaymentConfirmed(), 409, 'Workspace aktif setelah pembayaran terkonfirmasi.');

        $page = $this->timeline->page(
            $project,
            $request->query('before_id') ? (int) $request->query('before_id') : null,
            (int) $request->query('limit', ProjectTimeline::DEFAULT_LIMIT),
        );

        return response()->json(['status' => 'success', 'data' => $page['data'], 'meta' => ['has_more' => $page['has_more']]]);
    }
}
