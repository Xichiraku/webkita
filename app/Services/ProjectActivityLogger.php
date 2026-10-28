<?php

namespace App\Services;

use App\Events\ProjectActivityCreated;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use App\Support\ActivityPresenter;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Satu-satunya pintu penulisan riwayat aktivitas (append-only). Panggil di dalam transaksi database
 * yang sama dengan perubahan datanya, supaya log ikut dibatalkan bila transaksi gagal.
 */
class ProjectActivityLogger
{
    /**
     * Catat aktivitas oleh pengguna. $actor boleh dikosongkan bila ada pengguna yang login;
     * aksi tanpa pengguna (webhook, job) WAJIB memakai logSystem().
     *
     * @param  array<string, mixed>  $meta
     */
    public function log(Project $project, string $type, array $meta = [], ?User $actor = null, ?CarbonInterface $at = null): ProjectActivity
    {
        $actor ??= auth()->user();

        if ($actor === null) {
            throw new InvalidArgumentException('Aktivitas tanpa pengguna harus dicatat dengan logSystem().');
        }

        return $this->write($project, $type, $meta, $actor->id, $at);
    }

    /**
     * Catat aktivitas yang dilakukan sistem (tampil sebagai "Sistem").
     *
     * @param  array<string, mixed>  $meta
     */
    public function logSystem(Project $project, string $type, array $meta = [], ?CarbonInterface $at = null): ProjectActivity
    {
        return $this->write($project, $type, $meta, null, $at);
    }

    /** @param  array<string, mixed>  $meta */
    private function write(Project $project, string $type, array $meta, ?int $actorId, ?CarbonInterface $at): ProjectActivity
    {
        if (! ActivityPresenter::isValid($type)) {
            throw new InvalidArgumentException("Tipe aktivitas tidak dikenal: {$type}");
        }

        $activity = new ProjectActivity;
        $activity->project_id = $project->id;
        $activity->actor_id = $actorId;
        $activity->type = $type;
        $activity->meta = $meta;
        $activity->created_at = $at ? Carbon::instance($at) : now();
        $activity->save();

        ProjectActivityCreated::dispatch($activity->load('actor'));

        return $activity;
    }
}
