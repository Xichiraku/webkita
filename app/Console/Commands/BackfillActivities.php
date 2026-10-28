<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectActivityLogger;
use App\Support\Price;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('activities:backfill')]
#[Description('Isi riwayat aktivitas untuk proyek lama yang belum punya log (aman dijalankan ulang)')]
class BackfillActivities extends Command
{
    public function handle(ProjectActivityLogger $logger): int
    {
        $done = 0;

        Project::withTrashed()
            ->whereDoesntHave('activities')
            ->with(['user', 'developer', 'payments'])
            ->orderBy('id')
            ->each(function (Project $project) use ($logger, &$done) {
                DB::transaction(function () use ($project, $logger) {
                    foreach ($this->events($project) as [$at, $type, $meta, $actor]) {
                        $actor ? $logger->log($project, $type, $meta, $actor, $at) : $logger->logSystem($project, $type, $meta, $at);
                    }
                });
                $done++;
            });

        $this->components->info("Riwayat aktivitas diisi untuk {$done} proyek.");

        return self::SUCCESS;
    }

    /**
     * Rekonstruksi aktivitas dari data yang ada, terurut menurut waktu (id log naik = urutan waktu).
     *
     * @return list<array{0: CarbonInterface, 1: string, 2: array<string, mixed>, 3: User|null}>
     */
    private function events(Project $project): array
    {
        $events = [[$project->created_at, 'project.created', ['plan' => $project->plan_title ?: null], $project->user]];

        if ($project->invoice_issued_at) {
            $events[] = [$project->invoice_issued_at, 'price.set', [
                'amount' => Price::parse($project->plan_price_range), 'invoice_number' => $project->invoice_number,
            ], null];
        }

        foreach ($project->payments->where('status', PaymentStatus::Paid) as $payment) {
            $events[] = [$payment->paid_at ?? $payment->updated_at, 'payment.paid', [
                'order_id' => $payment->order_id, 'amount' => (int) round((float) $payment->amount), 'method' => $payment->payment_type,
            ], null];
        }

        if ($project->developer) {
            $events[] = [$project->assigned_at ?? $project->updated_at, 'developer.assigned', [
                'developer_id' => $project->developer->id, 'developer_name' => $project->developer->name, 'previous_name' => null,
            ], null];
        }

        if ($project->status !== ProjectStatus::Pending) {
            $events[] = [$project->updated_at, 'status.changed', [
                'from' => ProjectStatus::Pending->value, 'to' => $project->status->value, 'reason' => $project->reject_reason, 'backfilled' => true,
            ], null];
        }

        usort($events, fn ($a, $b) => $a[0]->getTimestamp() <=> $b[0]->getTimestamp());

        return $events;
    }
}
