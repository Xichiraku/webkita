<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\MilestoneResource;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Policies\ProjectWorkspacePolicy;
use App\Services\ChatService;
use App\Services\CommentService;
use App\Services\MilestoneService;
use App\Services\ProjectTimeline;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman Project Workspace dan daftar "Proyek Saya" untuk developer.
 * Akses mengikuti ProjectWorkspacePolicy: hanya klien pemilik dan developer yang ditugaskan.
 */
class WorkspaceController extends Controller
{
    /** Workspace untuk klien pemilik. */
    public function client(Request $request, Project $project): View
    {
        abort_unless($this->policy()->isClient($request->user(), $project), 403, 'Anda bukan pemilik proyek ini.');

        return $this->render($request, $project, 'client');
    }

    /** Workspace untuk developer yang ditugaskan (role developer atau admin). */
    public function developer(Request $request, Project $project): View
    {
        abort_unless($this->policy()->isDeveloper($request->user(), $project), 403, 'Anda tidak ditugaskan pada proyek ini.');

        return $this->render($request, $project, 'developer');
    }

    /** Daftar proyek yang ditugaskan kepada pengguna yang login. */
    public function developerIndex(Request $request): View
    {
        $projects = Project::where('developer_id', $request->user()->id)
            ->with(['user:id,username,first_name,last_name', 'payments'])
            ->latest('assigned_at')
            ->get();

        return view('developer.projects', ['projects' => $projects, 'unread' => app(ChatService::class)->unreadMap($request->user())]);
    }

    private function render(Request $request, Project $project, string $role): View
    {
        $project->loadMissing(['user:id,username,first_name,last_name,phone', 'developer:id,username,first_name,last_name', 'attachments']);

        if (! $project->isPaymentConfirmed()) {
            return view('pages.workspace-locked', ['project' => $project, 'role' => $role]);
        }

        $page = app(ProjectTimeline::class)->page($project);
        $comments = app(CommentService::class)->page($project, 30);
        $chat = app(ChatService::class);
        $chatState = $project->developer_id === null ? 'no_dev' : ($project->status === ProjectStatus::Done ? 'closed' : 'ok');
        $chatPage = $chatState === 'no_dev' ? null : $chat->page($request->user(), $project, 30);

        return view('pages.workspace', [
            'project' => $project,
            'role' => $role,
            // terlama di atas -> terbaru di bawah (R6.8)
            'activities' => array_reverse($page['data']),
            'activitiesHasMore' => $page['has_more'],
            'milestones' => MilestoneResource::collection(app(MilestoneService::class)->list($project))->resolve(),
            // terlama di atas -> terbaru di bawah, seperti riwayat aktivitas
            'comments' => CommentResource::collection($comments['data']->reverse()->values())->resolve($request),
            'commentsHasMore' => $comments['has_more'],
            'chatState' => $chatState,
            // terlama di atas -> terbaru di bawah
            'chatMessages' => $chatPage ? ChatMessageResource::collection($chatPage['data']->reverse()->values())->resolve($request) : [],
            'chatHasMore' => $chatPage['has_more'] ?? false,
            'chatLastRead' => $chatPage['last_read_message_id'] ?? 0,
            'chatUnread' => $chatPage ? $chat->unread($request->user()->id, $project) : 0,
            'resultRefs' => $project->updates()->where('type', ProjectUpdate::TYPE_RESULT)->orderBy('id')->get(['id', 'milestone_id', 'title'])->toArray(),
        ]);
    }

    private function policy(): ProjectWorkspacePolicy
    {
        return app(ProjectWorkspacePolicy::class);
    }
}
