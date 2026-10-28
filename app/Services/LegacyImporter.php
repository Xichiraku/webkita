<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Impor data dari ekspor CSV Supabase (skema lama) ke MySQL.
 *
 * Aturan penting:
 *  - ID dipertahankan (users/projects/payments/lampiran bigint, paket harga UUID), jadi relasi tidak berubah.
 *  - Hash password `$2b$`/`$2a$` (bcrypt Node) diubah ke prefiks `$2y$` yang dikenali PHP. Hash-nya sama,
 *    jadi pengguna lama tetap bisa login dengan password lama.
 *  - Token verifikasi & OTP lama dibuang (formatnya berbeda dan umurnya pendek); pengguna cukup minta kirim ulang.
 *  - Semua dalam satu transaksi: bila ada kegagalan, tidak ada data setengah jadi.
 */
class LegacyImporter
{
    /** Urutan impor mengikuti foreign key. */
    public const TABLES = ['users', 'pricing_plans', 'plan_features', 'plan_not_included', 'projects', 'payments', 'project_attachments'];

    /** Kolom tiap tabel: nama => tipe (int|str|bool|ts|dec|hash). Kolom di luar daftar diabaikan (mis. token_version). */
    private const COLUMNS = [
        'users' => [
            'id' => 'int', 'username' => 'str', 'first_name' => 'str', 'last_name' => 'str', 'email' => 'str', 'phone' => 'str',
            'password' => 'hash', 'group_id' => 'int', 'role' => 'str', 'is_aktif' => 'str', 'subscribe_newsletter' => 'bool',
            'email_verified_at' => 'ts', 'reset_otp_attempts' => 'int', 'created_at' => 'ts', 'updated_at' => 'ts', 'deleted_at' => 'ts',
        ],
        'pricing_plans' => [
            'id' => 'str', 'title' => 'str', 'tier' => 'str', 'min_price' => 'dec', 'max_price' => 'dec', 'price_range' => 'str',
            'timeline' => 'str', 'badge' => 'str', 'description' => 'str', 'upgrade_note' => 'str', 'is_active' => 'bool',
            'sort_order' => 'int', 'created_at' => 'ts', 'updated_at' => 'ts',
        ],
        'plan_features' => ['id' => 'str', 'plan_id' => 'str', 'feature' => 'str', 'sort_order' => 'int'],
        'plan_not_included' => ['id' => 'str', 'plan_id' => 'str', 'feature' => 'str', 'sort_order' => 'int'],
        'projects' => [
            'id' => 'int', 'user_id' => 'int', 'plan_title' => 'str', 'price_range' => 'str', 'plan_price_range' => 'str',
            'project_title' => 'str', 'category' => 'str', 'description' => 'str', 'skills' => 'str', 'contact_name' => 'str',
            'contact_phone' => 'str', 'additional_notes' => 'str', 'status' => 'str', 'reject_reason' => 'str', 'invoice_number' => 'str',
            'invoice_issued_at' => 'ts', 'created_at' => 'ts', 'updated_at' => 'ts', 'deleted_at' => 'ts',
        ],
        'payments' => [
            'id' => 'int', 'project_id' => 'int', 'user_id' => 'int', 'order_id' => 'str', 'amount' => 'dec', 'status' => 'str',
            'snap_token' => 'str', 'snap_url' => 'str', 'paid_at' => 'ts', 'transaction_id' => 'str', 'payment_type' => 'str',
            'created_at' => 'ts', 'updated_at' => 'ts',
        ],
        'project_attachments' => [
            'id' => 'int', 'project_id' => 'int', 'file_name' => 'str', 'file_size' => 'int', 'file_type' => 'str',
            'storage_path' => 'str', 'created_at' => 'ts',
        ],
    ];

    /** Kolom wajib terisi (setelah konversi). */
    private const REQUIRED = [
        'users' => ['id', 'username', 'email', 'password'],
        'pricing_plans' => ['id', 'title', 'tier', 'min_price', 'price_range'],
        'plan_features' => ['id', 'plan_id', 'feature'],
        'plan_not_included' => ['id', 'plan_id', 'feature'],
        'projects' => ['id', 'user_id', 'project_title', 'category', 'description', 'skills', 'contact_name', 'contact_phone'],
        'payments' => ['id', 'project_id', 'user_id', 'order_id', 'amount'],
        'project_attachments' => ['id', 'project_id', 'file_name', 'file_size', 'file_type', 'storage_path'],
    ];

    /** Kolom NOT NULL bertipe teks yang bila kosong diisi ''. */
    private const BLANK_OK = [
        'users' => ['first_name', 'last_name', 'phone'],
        'projects' => ['plan_title', 'price_range'],
    ];

    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<string, int> */
    private array $sourceCounts = [];

    public function __construct(private ?string $filesDir = null) {}

    /**
     * @return array{tables: array<string, array{source:int, imported:int, skipped:int}>, errors: array<string, list<string>>, warnings: list<string>, checks: array<string, mixed>}
     */
    public function run(string $dir, bool $dryRun = false, bool $allowUpdate = false, bool $skipInvalid = false): array
    {
        $this->errors = [];
        $this->warnings = [];

        // 1. Baca & konversi semua CSV
        $data = [];
        foreach (self::TABLES as $table) {
            $data[$table] = $this->load($dir, $table);
            $this->sourceCounts[$table] = count($data[$table]);
        }

        // 2. Validasi (relasi, nilai enum, keunikan)
        $data = $this->validate($data);

        $failed = $this->errorCount() > 0;
        $result = ['tables' => [], 'errors' => $this->errors, 'warnings' => $this->warnings, 'checks' => []];

        foreach (self::TABLES as $t) {
            $result['tables'][$t] = ['source' => $this->sourceCounts[$t], 'imported' => 0, 'skipped' => $this->sourceCounts[$t] - count($data[$t])];
        }

        if ($failed && ! $skipInvalid) {
            return $result;
        }

        if (! $allowUpdate) {
            $filled = array_filter(self::TABLES, fn ($t) => DB::table($t)->exists());
            if ($filled) {
                throw new InvalidArgumentException('Tabel tujuan sudah berisi data ('.implode(', ', $filled).'). Kosongkan dulu (php artisan migrate:fresh) atau pakai --update.');
            }
        }

        // 3. Tulis dalam satu transaksi (dibatalkan bila dry-run)
        DB::beginTransaction();
        try {
            foreach (self::TABLES as $t) {
                foreach (array_chunk($data[$t], 500) as $chunk) {
                    DB::table($t)->upsert($chunk, ['id'], array_keys($chunk[0]));
                }
                $result['tables'][$t]['imported'] = count($data[$t]);
            }

            $result['checks'] = $this->checks($data);

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if (! $dryRun) {
            $this->copyFiles($data['project_attachments']);
        }

        $result['warnings'] = $this->warnings;

        return $result;
    }

    // ───────────────────────── membaca CSV ─────────────────────────

    /** @return list<array<string, mixed>> */
    private function load(string $dir, string $table): array
    {
        $path = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$table.'.csv';

        if (! is_file($path)) {
            if (in_array($table, ['users', 'projects'], true)) {
                throw new InvalidArgumentException("File {$table}.csv tidak ditemukan di {$dir}");
            }
            $this->warnings[] = "{$table}.csv tidak ada, dilewati.";

            return [];
        }

        $h = fopen($path, 'r');
        $header = fgetcsv($h, 0, ',', '"', '');
        if (! $header) {
            return [];
        }
        $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF"); // BOM
        $header = array_map('trim', $header);

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($h, 0, ',', '"', '')) !== false) {
            $line++;
            if ($cells === [null]) {
                continue; // baris kosong
            }
            if (count($cells) !== count($header)) {
                $this->errors[$table][] = "baris {$line}: jumlah kolom ".count($cells).' (seharusnya '.count($header).')';

                continue;
            }
            $raw = array_combine($header, $cells);
            $row = $this->convert($table, $raw, $line);
            if ($row !== null) {
                $rows[] = $row;
            }
        }
        fclose($h);

        return $rows;
    }

    /** @return array<string, mixed>|null */
    private function convert(string $table, array $raw, int $line): ?array
    {
        $row = [];
        foreach (self::COLUMNS[$table] as $col => $type) {
            if (! array_key_exists($col, $raw)) {
                continue;
            }
            $v = $raw[$col];
            $blank = $v === null || $v === '' || strtolower($v) === 'null' || $v === '\N';

            try {
                $row[$col] = match (true) {
                    $blank && in_array($col, self::BLANK_OK[$table] ?? [], true) => '',
                    $blank => null,
                    $type === 'int' => $this->int($v),
                    $type === 'dec' => $this->decimal($v),
                    $type === 'bool' => in_array(strtolower($v), ['t', 'true', '1', 'yes'], true) ? 1 : 0,
                    $type === 'ts' => Carbon::parse($v)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                    $type === 'hash' => preg_replace('/^\$2[abx]\$/', '\$2y\$', $v),
                    default => $v,
                };
            } catch (\Throwable) {
                $this->errors[$table][] = "baris {$line}: nilai '{$v}' tidak valid untuk kolom {$col}";

                return null;
            }
        }

        foreach (self::REQUIRED[$table] as $col) {
            if (! isset($row[$col]) || $row[$col] === '') {
                $this->errors[$table][] = "baris {$line}: kolom {$col} wajib diisi";

                return null;
            }
        }

        // Nilai bawaan skema baru untuk kolom NOT NULL yang tidak ada di ekspor.
        return match ($table) {
            'users' => $row + ['group_id' => 2, 'role' => 'user', 'is_aktif' => 'N', 'subscribe_newsletter' => 0, 'reset_otp_attempts' => 0],
            'projects' => $row + ['status' => 'pending'],
            'payments' => $row + ['status' => 'pending'],
            default => $row,
        };
    }

    private function int(string $v): int
    {
        if (! preg_match('/^-?\d+(\.0+)?$/', trim($v))) {
            throw new InvalidArgumentException($v);
        }

        return (int) $v;
    }

    private function decimal(string $v): string
    {
        if (! is_numeric($v)) {
            throw new InvalidArgumentException($v);
        }

        return (string) $v;
    }

    // ───────────────────────── validasi ─────────────────────────

    /**
     * @param  array<string, list<array<string, mixed>>>  $data
     * @return array<string, list<array<string, mixed>>>
     */
    private function validate(array $data): array
    {
        // Duplikat unik di dalam berkas
        foreach ([['users', 'username'], ['users', 'email'], ['users', 'id'], ['projects', 'id'], ['payments', 'order_id'], ['payments', 'id']] as [$t, $c]) {
            $seen = [];
            $data[$t] = array_values(array_filter($data[$t], function ($r) use ($t, $c, &$seen) {
                $key = strtolower((string) ($r[$c] ?? ''));
                if (isset($seen[$key])) {
                    $this->errors[$t][] = "id {$r['id']}: {$c} '{$r[$c]}' duplikat";

                    return false;
                }
                $seen[$key] = true;

                return true;
            }));
        }

        // Enum & relasi
        $data['users'] = $this->keep($data['users'], 'users', function ($r) {
            if (! Role::tryFrom((string) ($r['role'] ?? ''))) {
                $this->warnings[] = "users id {$r['id']}: role '{$r['role']}' tidak dikenal, diubah ke 'user'.";
            }

            return true;
        });
        foreach ($data['users'] as &$u) {
            $u['role'] = (Role::tryFrom((string) ($u['role'] ?? '')) ?? Role::User)->value;
            $u['is_aktif'] = ($u['is_aktif'] ?? 'N') === 'Y' ? 'Y' : 'N';
        }
        unset($u);

        $userIds = array_flip(array_column($data['users'], 'id'));
        $planIds = array_flip(array_column($data['pricing_plans'], 'id'));

        foreach (['plan_features', 'plan_not_included'] as $t) {
            $data[$t] = $this->keep($data[$t], $t, fn ($r) => isset($planIds[$r['plan_id']]) ?: $this->bad($t, $r, "plan_id {$r['plan_id']} tidak ada di pricing_plans"));
        }

        $data['projects'] = $this->keep($data['projects'], 'projects', function ($r) use ($userIds) {
            if (! isset($userIds[$r['user_id']])) {
                return $this->bad('projects', $r, "user_id {$r['user_id']} tidak ada di users");
            }
            if (! ProjectStatus::tryFrom((string) $r['status'])) {
                return $this->bad('projects', $r, "status '{$r['status']}' tidak dikenal");
            }

            return true;
        });
        $projectIds = array_flip(array_column($data['projects'], 'id'));

        $data['payments'] = $this->keep($data['payments'], 'payments', function ($r) use ($userIds, $projectIds) {
            if (! isset($projectIds[$r['project_id']]) || ! isset($userIds[$r['user_id']])) {
                return $this->bad('payments', $r, 'project_id/user_id tidak ada');
            }
            if (! PaymentStatus::tryFrom((string) $r['status'])) {
                return $this->bad('payments', $r, "status '{$r['status']}' tidak dikenal");
            }

            return true;
        });

        $data['project_attachments'] = $this->keep($data['project_attachments'], 'project_attachments', fn ($r) => isset($projectIds[$r['project_id']]) ?: $this->bad('project_attachments', $r, "project_id {$r['project_id']} tidak ada"));

        // Pembayaran lunas lama tanpa paid_at: pakai updated_at (sama dengan migrasi SQL lama).
        foreach ($data['payments'] as &$p) {
            if ($p['status'] === 'paid' && empty($p['paid_at'])) {
                $p['paid_at'] = $p['updated_at'] ?? $p['created_at'] ?? null;
            }
        }
        unset($p);

        // Kolom timestamp NOT NULL: isi dengan waktu sekarang bila ekspor tidak memuatnya.
        $now = now()->format('Y-m-d H:i:s');
        foreach (['users', 'projects', 'payments', 'pricing_plans'] as $t) {
            foreach ($data[$t] as &$r) {
                $r['created_at'] ??= $now;
                $r['updated_at'] ??= $r['created_at'];
            }
            unset($r);
        }
        foreach ($data['project_attachments'] as &$r) {
            $r['created_at'] ??= $now;
        }
        unset($r);

        // Samakan kunci kolom semua baris (upsert butuh bentuk seragam).
        foreach (self::TABLES as $t) {
            $cols = array_keys(self::COLUMNS[$t]);
            $data[$t] = array_map(function ($r) use ($cols, $t) {
                $out = [];
                foreach ($cols as $c) {
                    if (array_key_exists($c, $r)) {
                        $out[$c] = $r[$c];
                    } elseif (in_array($c, self::BLANK_OK[$t] ?? [], true)) {
                        $out[$c] = '';
                    } else {
                        $out[$c] = null;
                    }
                }

                return $out + array_diff_key($r, $out);
            }, $data[$t]);
        }

        return $data;
    }

    private function errorCount(): int
    {
        return array_sum(array_map('count', $this->errors));
    }

    private function bad(string $table, array $row, string $msg): bool
    {
        $this->errors[$table][] = "id {$row['id']}: {$msg}";

        return false;
    }

    /** @param list<array<string, mixed>> $rows */
    private function keep(array $rows, string $table, callable $test): array
    {
        return array_values(array_filter($rows, $test));
    }

    // ───────────────────────── pemeriksaan hasil ─────────────────────────

    /**
     * Pemeriksaan setelah tulis (sebelum commit): jumlah baris & total nominal harus cocok dengan sumber.
     *
     * @param  array<string, list<array<string, mixed>>>  $data
     * @return array<string, mixed>
     */
    private function checks(array $data): array
    {
        $checks = [];
        foreach (self::TABLES as $t) {
            $ids = array_column($data[$t], 'id');
            $inDb = $ids ? DB::table($t)->whereIn('id', $ids)->count() : 0;
            $checks["{$t}.rows"] = ['expected' => count($ids), 'actual' => $inDb, 'ok' => $inDb === count($ids)];
        }

        $expectedSum = array_sum(array_map(fn ($r) => (float) $r['amount'], $data['payments']));
        $actualSum = (float) DB::table('payments')->whereIn('id', array_column($data['payments'], 'id') ?: [0])->sum('amount');
        $checks['payments.sum'] = ['expected' => round($expectedSum, 2), 'actual' => round($actualSum, 2), 'ok' => abs($expectedSum - $actualSum) < 0.01];

        $paid = count(array_filter($data['payments'], fn ($r) => $r['status'] === 'paid'));
        $checks['payments.paid'] = ['expected' => $paid, 'actual' => DB::table('payments')->where('status', 'paid')->whereIn('id', array_column($data['payments'], 'id') ?: [0])->count(), 'ok' => true];
        $checks['payments.paid']['ok'] = $checks['payments.paid']['expected'] === $checks['payments.paid']['actual'];

        foreach ($checks as $name => $c) {
            if (! $c['ok']) {
                throw new \RuntimeException("Pemeriksaan gagal ({$name}): diharapkan {$c['expected']}, hasil {$c['actual']}. Transaksi dibatalkan.");
            }
        }

        return $checks;
    }

    // ───────────────────────── berkas lampiran ─────────────────────────

    /** Salin berkas lampiran dari folder ekspor Storage ke disk privat aplikasi. */
    private function copyFiles(array $attachments): void
    {
        if (! $this->filesDir) {
            if ($attachments) {
                $this->warnings[] = count($attachments).' lampiran tercatat di database, tetapi berkasnya belum disalin (pakai --files=<folder>).';
            }

            return;
        }

        $disk = Storage::disk('local');
        $missing = 0;

        foreach ($attachments as $a) {
            $src = rtrim($this->filesDir, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $a['storage_path']);

            // Cegah path traversal dari data CSV.
            if (str_contains($a['storage_path'], '..') || ! is_file($src)) {
                $missing++;
                $this->warnings[] = "lampiran id {$a['id']}: berkas {$a['storage_path']} tidak ditemukan.";

                continue;
            }

            $disk->put($a['storage_path'], file_get_contents($src));
        }

        if ($missing === 0) {
            $this->warnings[] = 'Semua '.count($attachments).' berkas lampiran berhasil disalin.';
        }
    }
}
