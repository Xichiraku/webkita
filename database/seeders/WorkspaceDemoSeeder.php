<?php

namespace Database\Seeders;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Services\ChatService;
use App\Services\CommentService;
use App\Services\MilestoneService;
use App\Services\MilestoneUpdateService;
use App\Services\ReviewService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;

/**
 * Data uji Project Workspace: dua proyek berbayar yang sudah berjalan lewat alur sebenarnya
 * (service yang sama dengan API), sehingga log, progres, kuota revisi, dan chat konsisten.
 *
 *  - "Aplikasi toko online demo": status process, milestone di setiap keadaan (disetujui, menunggu review
 *    setelah satu revisi, dikerjakan, direncanakan), komentar, dan chat aktif.
 *  - "Landing page selesai demo": semua milestone disetujui, proyek done, chat hanya-baca.
 *
 * Aman dijalankan ulang: proyek yang judulnya sudah ada dilewati.
 */
class WorkspaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $client = User::where('email', 'demo@webkita.local')->first();
        $dev = User::where('email', 'admin@webkita.local')->first();

        if (! $client || ! $dev) {
            return;
        }

        // Alur nyata memicu event siaran dan email; untuk data uji cukup dijalankan sinkron tanpa mengirimnya.
        $queue = config('queue.default');
        $broadcast = config('broadcasting.default');
        config(['queue.default' => 'sync', 'broadcasting.default' => 'null']);
        Mail::fake();

        try {
            $this->activeProject($client, $dev);
            $this->finishedProject($client, $dev);
        } finally {
            config(['queue.default' => $queue, 'broadcasting.default' => $broadcast]);
        }
    }

    private function activeProject(User $client, User $dev): void
    {
        if (Project::where('project_title', 'Aplikasi toko online demo')->exists()) {
            return;
        }

        $project = $this->paidProject($client, $dev, 'Aplikasi toko online demo', 'Business', 'Rp 8.000.000', 8000000, 'WS-DEMO-001', 6);

        $ids = $this->milestones($project, $dev, ['Desain antarmuka', 'Halaman toko', 'Keranjang dan pembayaran', 'Pengujian dan peluncuran']);

        // 1. Desain: progres lalu hasil, langsung disetujui klien.
        $this->progress($dev, $ids[0], 50, 'Wireframe semua halaman selesai.');
        $result = $this->result($dev, $ids[0], 'Desain final', 'Mockup semua halaman siap ditinjau.', ['https://staging.contoh.test/desain']);
        app(ReviewService::class)->approve($client, $result);

        // 2. Halaman toko: hasil pertama direvisi, hasil kedua menunggu review.
        $this->progress($dev, $ids[1], 60, 'Daftar produk dan detail produk berjalan.');
        $first = $this->result($dev, $ids[1], 'Halaman toko v1', 'Daftar dan detail produk sudah dapat dicoba.', ['https://staging.contoh.test/toko']);
        app(ReviewService::class)->requestRevision($client, $first, 'Gambar produk terpotong di layar ponsel, tolong diperbaiki.');
        app(CommentService::class)->post($project, $dev, 'Baik, saya perbaiki rasio gambarnya.', $first->id);
        $this->result($dev, $ids[1], 'Halaman toko v2', 'Rasio gambar sudah diperbaiki untuk semua ukuran layar.', ['https://staging.contoh.test/toko-v2']);

        // 3. Keranjang: sedang dikerjakan. 4. Pengujian: masih direncanakan.
        $this->progress($dev, $ids[2], 30, 'Keranjang belanja sudah menyimpan item.');

        app(CommentService::class)->post($project, $client, 'Terima kasih, desainnya sudah sesuai harapan.');

        $chat = app(ChatService::class);
        $chat->send($client, $project, 'Halo, kapan kira-kira halaman pembayaran bisa dicoba?');
        $chat->send($dev, $project, 'Halo! Perkiraan akhir minggu ini, saya kabari begitu siap.');
        $chat->send($client, $project, 'Siap, terima kasih.');
    }

    private function finishedProject(User $client, User $dev): void
    {
        if (Project::where('project_title', 'Landing page selesai demo')->exists()) {
            return;
        }

        $project = $this->paidProject($client, $dev, 'Landing page selesai demo', 'Starter', 'Rp 750.000', 750000, 'WS-DEMO-002', 20);

        $ids = $this->milestones($project, $dev, ['Desain landing page', 'Pengembangan dan peluncuran']);

        // Chat harus dikirim sebelum proyek selesai; setelah itu chat hanya-baca.
        $chat = app(ChatService::class);
        $first = $this->result($dev, $ids[0], 'Hasil tahap 1', 'Desain landing page sudah selesai.', ['https://staging.contoh.test/landing']);
        $chat->send($dev, $project, 'Desain sudah saya serahkan, mohon ditinjau.');
        $chat->send($client, $project, 'Baik, saya tinjau sekarang.');
        app(ReviewService::class)->approve($client, $first);

        $second = $this->result($dev, $ids[1], 'Hasil tahap 2', 'Landing page sudah diluncurkan.', ['https://staging.contoh.test/landing']);
        app(ReviewService::class)->approve($client, $second);

        app(CommentService::class)->post($project->refresh(), $client, 'Terima kasih, hasilnya bagus!');
    }

    private function paidProject(User $client, User $dev, string $title, string $plan, string $price, int $amount, string $orderId, int $ageDays): Project
    {
        $created = now()->subDays($ageDays);

        $project = new Project([
            'user_id' => $client->id, 'plan_title' => $plan, 'price_range' => $price, 'plan_price_range' => $price,
            'project_title' => $title, 'category' => 'website', 'description' => "{$title} untuk pengujian Project Workspace.",
            'skills' => 'Laravel, Tailwind CSS', 'contact_name' => $client->first_name.' '.$client->last_name, 'contact_phone' => '081234567890',
            'additional_notes' => 'Data uji Workspace.',
        ]);
        $project->status = ProjectStatus::Approved;
        $project->created_at = $created;
        $project->updated_at = $created;
        $project->save();

        $project->forceFill([
            'invoice_issued_at' => $created,
            'invoice_number' => sprintf('INV-%04d-%d', $project->id, $created->year),
            'developer_id' => $dev->id,
            'assigned_at' => $created->copy()->addDay(),
        ])->save();

        $payment = new Payment(['project_id' => $project->id, 'user_id' => $client->id, 'order_id' => $orderId, 'amount' => $amount]);
        $payment->status = PaymentStatus::Paid;
        $payment->paid_at = $created->copy()->addDays(2);
        $payment->created_at = $created->copy()->addDays(2);
        $payment->updated_at = $created->copy()->addDays(2);
        $payment->save();

        return $project;
    }

    /**
     * @param  list<string>  $titles
     * @return list<int> id milestone sesuai urutan
     */
    private function milestones(Project $project, User $dev, array $titles): array
    {
        return array_map(fn (string $t) => app(MilestoneService::class)->create($project, ['title' => $t], $dev)->id, $titles);
    }

    private function progress(User $dev, int $milestoneId, int $percent, string $note): void
    {
        app(MilestoneUpdateService::class)->submit($dev, ProjectMilestone::findOrFail($milestoneId), ['type' => 'progress', 'percent' => $percent, 'note' => $note]);
    }

    /** @param  list<string>  $links */
    private function result(User $dev, int $milestoneId, string $title, string $note, array $links): ProjectUpdate
    {
        return app(MilestoneUpdateService::class)->submit($dev, ProjectMilestone::findOrFail($milestoneId), [
            'type' => 'result', 'title' => $title, 'note' => $note, 'links' => $links,
        ]);
    }
}
