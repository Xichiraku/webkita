<?php

namespace Database\Seeders;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoProjectSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'demo@webkita.local')->first();
        $admin = User::where('email', 'admin@webkita.local')->first();
        if (! $user) {
            return;
        }

        // [plan, harga, judul, kategori, deskripsi, skills, status, umur(hari), order_id, status bayar]
        $samples = [
            ['Professional', 'Rp 3.000.000', 'Website katalog produk demo', 'website',
                'Website katalog produk untuk demonstrasi transaksi WebKita.', 'React, UI/UX, SEO',
                ProjectStatus::Approved, 12, 'DEMO-WEBKITA-001', 3000000, PaymentStatus::Paid, 11],
            ['Starter', 'Rp 750.000', 'Landing page campaign demo', 'website',
                'Landing page campaign untuk demonstrasi transaksi WebKita.', 'HTML, Tailwind CSS',
                ProjectStatus::Process, 5, 'DEMO-WEBKITA-002', 750000, PaymentStatus::Pending, 4],
            ['Business', 'Rp 8.000.000', 'Aplikasi dashboard demo', 'website',
                'Dashboard bisnis untuk demonstrasi transaksi WebKita.', 'Laravel, MySQL',
                ProjectStatus::Pending, 2, 'DEMO-WEBKITA-003', 8000000, PaymentStatus::Failed, 1],
        ];

        foreach ($samples as [$plan, $price, $title, $cat, $desc, $skills, $status, $age, $orderId, $amount, $payStatus, $payAge]) {
            if (Project::where('user_id', $user->id)->where('project_title', $title)->exists()) {
                continue;
            }

            $created = now()->subDays($age);
            $project = new Project([
                'user_id' => $user->id, 'plan_title' => $plan, 'price_range' => $price,
                'plan_price_range' => $price, 'project_title' => $title, 'category' => $cat,
                'description' => $desc, 'skills' => $skills,
                'contact_name' => $user->first_name.' '.$user->last_name, 'contact_phone' => '081234567890',
                'additional_notes' => 'Data dummy untuk tampilan admin.',
            ]);
            $project->status = $status;
            $project->created_at = $created;
            $project->updated_at = $created;
            $project->save();

            $project->forceFill([
                'invoice_issued_at' => $created,
                'invoice_number' => sprintf('INV-%04d-%d', $project->id, $created->year),
            ])->save();

            // Proyek demo yang sudah dibayar ditugaskan ke admin-demo (admin = developer), agar Workspace bisa dicoba.
            if ($payStatus === PaymentStatus::Paid && $admin) {
                $project->forceFill(['developer_id' => $admin->id, 'assigned_at' => $created->copy()->addDay()])->save();

                // Rencana kerja awal; semuanya masih "planned" sehingga update pertama memindahkan proyek ke "process".
                foreach (['Desain antarmuka', 'Pengembangan fitur utama', 'Pengujian dan peluncuran'] as $i => $milestoneTitle) {
                    $milestone = new ProjectMilestone(['title' => $milestoneTitle]);
                    $milestone->project_id = $project->id;
                    $milestone->position = $i + 1;
                    $milestone->save();
                }
            }

            $payAt = now()->subDays($payAge);
            $payment = new Payment([
                'project_id' => $project->id, 'user_id' => $user->id, 'order_id' => $orderId, 'amount' => $amount,
            ]);
            $payment->status = $payStatus;
            $payment->paid_at = $payStatus === PaymentStatus::Paid ? $payAt : null;
            $payment->created_at = $payAt;
            $payment->updated_at = $payAt;
            $payment->save();
        }
    }
}
