<?php

namespace App\Console\Commands;

use App\Services\LegacyImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('webkita:import-legacy {path : Folder berisi ekspor CSV Supabase} {--files= : Folder ekspor Supabase Storage (bucket project-files) untuk lampiran} {--dry-run : Validasi dan simulasikan tanpa menyimpan apa pun} {--update : Boleh menimpa baris dengan ID yang sama pada tabel yang sudah berisi} {--skip-invalid : Lewati baris bermasalah, impor sisanya}')]
#[Description('Impor data lama (Supabase/PostgreSQL, CSV) ke database MySQL')]
class ImportLegacyData extends Command
{
    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->components->info(($dry ? '[DRY RUN] ' : '').'Membaca ekspor dari '.$this->argument('path'));

        try {
            $result = (new LegacyImporter($this->option('files')))->run(
                $this->argument('path'),
                $dry,
                (bool) $this->option('update'),
                (bool) $this->option('skip-invalid'),
            );
        } catch (InvalidArgumentException|\RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Tabel', 'Di sumber', 'Diimpor', 'Dilewati'],
            collect($result['tables'])->map(fn ($t, $name) => [$name, $t['source'], $t['imported'], $t['skipped']])->values()->all(),
        );

        foreach ($result['errors'] as $table => $errors) {
            $this->components->warn("{$table}: ".count($errors).' baris bermasalah');
            foreach (array_slice($errors, 0, 20) as $e) {
                $this->line("  - {$e}");
            }
            if (count($errors) > 20) {
                $this->line('  … dan '.(count($errors) - 20).' lainnya');
            }
        }

        foreach ($result['warnings'] as $w) {
            $this->line("  ! {$w}");
        }

        $hasErrors = array_sum(array_map('count', $result['errors'])) > 0;

        if ($hasErrors && ! $this->option('skip-invalid')) {
            $this->components->error('Ada baris bermasalah. Tidak ada yang diimpor. Perbaiki ekspor, atau pakai --skip-invalid.');

            return self::FAILURE;
        }

        if ($result['checks']) {
            $this->components->info('Pemeriksaan: '.count($result['checks']).' butir lolos (jumlah baris, total nominal pembayaran, jumlah transaksi lunas).');
        }

        $dry
            ? $this->components->info('Dry run selesai: data valid dan dapat diimpor. Tidak ada yang disimpan.')
            : $this->components->info('Impor selesai.');

        return self::SUCCESS;
    }
}
