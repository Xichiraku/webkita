<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            PricingPlanSeeder::class,
            DemoProjectSeeder::class,
            WorkspaceDemoSeeder::class,
        ]);

        // Riwayat aktivitas untuk proyek demo
        Artisan::call('activities:backfill');
    }
}
