<?php

namespace Database\Seeders;

use App\Models\PricingPlan;
use Illuminate\Database\Seeder;

class PricingPlanSeeder extends Seeder
{
    public function run(): void
    {
        if (PricingPlan::exists()) {
            return;
        }

        $plans = [
            ['Starter', 'starter', 500000, 1500000, 'Rp 500.000 – Rp 1.500.000', '1–2 minggu', null,
                'Website profesional untuk memulai bisnis online.', 'Butuh fitur lebih? Pilih paket Professional.',
                ['Desain responsif', 'Hingga 5 halaman', 'Optimasi SEO dasar']],
            ['Professional', 'professional', 1500000, 5000000, 'Rp 1.500.000 – Rp 5.000.000', '2–4 minggu', 'Paling populer',
                'Website bisnis dengan fitur lengkap dan desain khusus.', 'Tingkatkan ke Business untuk solusi skala besar.',
                ['Semua fitur Starter', 'Hingga 15 halaman', 'CMS dan integrasi', 'Optimasi SEO lanjutan']],
            ['Business', 'business', 5000000, 15000000, 'Rp 5.000.000 – Rp 15.000.000', '4–8 minggu', null,
                'Solusi digital lengkap untuk bisnis yang berkembang.', 'Hubungi kami untuk kebutuhan khusus.',
                ['Semua fitur Professional', 'Halaman dan fitur kustom', 'Integrasi pihak ketiga', 'Dukungan prioritas']],
            ['Enterprise', 'enterprise', 15000000, null, 'Mulai Rp 15.000.000', 'Sesuai kebutuhan', 'Custom',
                'Pengembangan khusus untuk kebutuhan enterprise.', 'Tim kami siap membantu menentukan ruang lingkup.',
                ['Solusi dan desain kustom', 'Integrasi sistem enterprise', 'Dukungan prioritas', 'Konsultasi teknis']],
        ];

        foreach ($plans as $i => [$title, $tier, $min, $max, $range, $timeline, $badge, $desc, $upgrade, $features]) {
            $plan = PricingPlan::create([
                'title' => $title, 'tier' => $tier, 'min_price' => $min, 'max_price' => $max,
                'price_range' => $range, 'timeline' => $timeline, 'badge' => $badge,
                'description' => $desc, 'upgrade_note' => $upgrade, 'is_active' => true, 'sort_order' => $i + 1,
            ]);

            foreach ($features as $n => $feature) {
                $plan->features()->create(['feature' => $feature, 'sort_order' => $n + 1]);
            }
        }
    }
}
