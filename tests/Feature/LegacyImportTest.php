<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PricingPlan;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private string $files;

    /** Hash bcrypt dari Supabase (prefiks $2b$) untuk "Demo1234!" — sama dengan supabase/seed.sql. */
    private const LEGACY_HASH = '$2b$12$DUbJCLLpUDiRrXENY5mjTuzhMOnyyqwvXXdoEB/cIcspTds8p1CTK';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $base = sys_get_temp_dir().'/webkita-import-'.uniqid();
        $this->dir = $base.'/csv';
        $this->files = $base.'/files';
        File::makeDirectory($this->dir, 0755, true);
        File::makeDirectory($this->files.'/attachments/1', 0755, true);
        $this->writeFixtures();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->dir));
        parent::tearDown();
    }

    private function csv(string $table, array $rows): void
    {
        $h = fopen("{$this->dir}/{$table}.csv", 'w');
        fwrite($h, "\xEF\xBB\xBF"); // BOM seperti beberapa ekspor
        foreach ($rows as $r) {
            fputcsv($h, $r, ',', '"', '');
        }
        fclose($h);
    }

    private function writeFixtures(): void
    {
        $this->csv('users', [
            ['id', 'username', 'first_name', 'last_name', 'email', 'phone', 'password', 'group_id', 'role', 'is_aktif', 'subscribe_newsletter', 'email_verified_at', 'verification_token', 'token_version', 'reset_otp', 'reset_otp_attempts', 'created_at', 'updated_at', 'deleted_at'],
            ['1', 'demo', 'Demo', '', 'demo@webkita.local', '', self::LEGACY_HASH, '2', 'user', 'Y', 't', '2026-10-20 03:00:00+00', '', '3', '', '0', '2026-10-20 03:00:00+00', '2026-10-21 03:00:00+00', ''],
            ['2', 'admin-demo', 'Admin', 'Demo', 'admin@webkita.local', '0811', self::LEGACY_HASH, '1', 'admin', 'Y', 'f', '2026-10-20 03:00:00+00', 'abc123plaintext', '0', '123456', '2', '2026-10-20 03:00:00+00', '2026-10-20 03:00:00+00', ''],
            ['3', 'dihapus', 'Si', 'Hapus', 'del@webkita.local', '', self::LEGACY_HASH, '2', 'aneh', 'N', 'f', '', '', '0', '', '0', '2026-10-20 03:00:00+00', '2026-10-20 03:00:00+00', '2026-10-25 03:00:00+00'],
        ]);

        $this->csv('pricing_plans', [
            ['id', 'title', 'tier', 'min_price', 'max_price', 'price_range', 'timeline', 'badge', 'description', 'upgrade_note', 'is_active', 'sort_order', 'created_at', 'updated_at'],
            ['0c5d3c1e-9b1a-4f0e-8c0a-111111111111', 'Starter', 'starter', '500000', '1500000', 'Rp 500.000 – Rp 1.500.000', '1–2 minggu', '', 'Mulai bisnis online.', '', 'true', '1', '2026-10-01 00:00:00+00', '2026-10-01 00:00:00+00'],
            ['0c5d3c1e-9b1a-4f0e-8c0a-222222222222', 'Enterprise', 'enterprise', '15000000', '', 'Mulai Rp 15.000.000', '', 'Custom', '', '', 'false', '2', '2026-10-01 00:00:00+00', '2026-10-01 00:00:00+00'],
        ]);
        $this->csv('plan_features', [
            ['id', 'plan_id', 'feature', 'sort_order'],
            ['11111111-0000-0000-0000-000000000001', '0c5d3c1e-9b1a-4f0e-8c0a-111111111111', 'Desain responsif', '1'],
            ['11111111-0000-0000-0000-000000000002', '0c5d3c1e-9b1a-4f0e-8c0a-111111111111', 'SEO dasar', '2'],
        ]);
        $this->csv('plan_not_included', [
            ['id', 'plan_id', 'feature', 'sort_order'],
            ['22222222-0000-0000-0000-000000000001', '0c5d3c1e-9b1a-4f0e-8c0a-111111111111', 'Hosting', '1'],
        ]);

        $this->csv('projects', [
            ['id', 'user_id', 'plan_title', 'price_range', 'plan_price_range', 'project_title', 'category', 'description', 'skills', 'contact_name', 'contact_phone', 'additional_notes', 'status', 'reject_reason', 'invoice_number', 'invoice_issued_at', 'created_at', 'updated_at', 'deleted_at'],
            ['10', '1', 'Starter', 'Rp 750.000', '750000', 'Toko "Batik"', 'website', "Baris pertama\nBaris kedua, dengan koma", 'Laravel, MySQL', 'Demo', '0812', '', 'approved', '', 'INV-0010-2026', '2026-10-21 03:00:00+00', '2026-10-20 03:00:00+00', '2026-10-21 03:00:00+00', ''],
            ['11', '1', '', '', '', 'Proyek ditolak', 'design', 'Deskripsi', 'Figma', 'Demo', '0812', 'Catatan', 'rejected', 'Di luar cakupan', '', '', '2026-10-22 03:00:00+00', '2026-10-22 03:00:00+00', '2026-10-23 03:00:00+00'],
        ]);

        $this->csv('payments', [
            ['id', 'project_id', 'user_id', 'order_id', 'amount', 'status', 'snap_token', 'snap_url', 'paid_at', 'transaction_id', 'payment_type', 'created_at', 'updated_at'],
            ['100', '10', '1', 'WEBKITA-10-1', '750000.00', 'paid', 'tok', 'https://x', '', 'trx1', 'qris', '2026-10-21 04:00:00+00', '2026-10-21 05:00:00+00'],
            ['101', '10', '1', 'WEBKITA-10-2', '750000', 'failed', '', '', '', '', '', '2026-10-21 06:00:00+00', '2026-10-21 06:00:00+00'],
        ]);

        $this->csv('project_attachments', [
            ['id', 'project_id', 'file_name', 'file_size', 'file_type', 'storage_path', 'created_at'],
            ['1000', '10', 'brief.pdf', '8', 'application/pdf', 'attachments/1/brief.pdf', '2026-10-21 03:00:00+00'],
            ['1001', '10', 'hilang.png', '3', 'image/png', 'attachments/1/hilang.png', '2026-10-21 03:00:00+00'],
        ]);
        file_put_contents("{$this->files}/attachments/1/brief.pdf", 'isi-pdf!');
    }

    private function import(array $extra = []): PendingCommand
    {
        return $this->artisan('webkita:import-legacy', ['path' => $this->dir, '--files' => $this->files] + $extra);
    }

    public function test_impor_lengkap_mempertahankan_id_dan_relasi(): void
    {
        $this->import()->assertSuccessful();

        $this->assertSame(3, User::withTrashed()->count());
        $this->assertSame(2, Project::withTrashed()->count());
        $this->assertSame(2, Payment::count());
        $this->assertSame(2, ProjectAttachment::count());
        $this->assertSame(2, PricingPlan::count());
        $this->assertSame(2, DB::table('plan_features')->count());
        $this->assertSame(1, DB::table('plan_not_included')->count());

        $project = Project::findOrFail(10);
        $this->assertSame(1, $project->user_id);
        $this->assertSame('Toko "Batik"', $project->project_title);
        $this->assertSame("Baris pertama\nBaris kedua, dengan koma", $project->description);
        $this->assertSame('750000', $project->plan_price_range);
        $this->assertSame('INV-0010-2026', $project->invoice_number);
        $this->assertSame(2, Payment::find(100)->project->user_id === 1 ? 2 : 0);
    }

    public function test_hash_password_lama_tetap_bisa_login(): void
    {
        $this->import()->assertSuccessful();

        $demo = User::findOrFail(1);
        $this->assertStringStartsWith('$2y$12$', $demo->password);
        $this->assertTrue(Hash::check('Demo1234!', $demo->password));

        $this->post('/login', ['login' => 'demo@webkita.local', 'password' => 'Demo1234!'])->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->post('/login', ['login' => 'admin-demo', 'password' => 'Demo1234!'])->assertRedirect('/dashboard');
    }

    public function test_konversi_nilai(): void
    {
        $this->import()->assertSuccessful();

        $demo = User::findOrFail(1);
        $this->assertTrue($demo->subscribe_newsletter);
        $this->assertSame('', $demo->last_name);
        $this->assertSame('', $demo->phone);
        $this->assertSame('Y', $demo->is_aktif);
        // 03:00 UTC = 10:00 WIB
        $this->assertSame('2026-10-20 10:00:00', $demo->email_verified_at->format('Y-m-d H:i:s'));

        $admin = User::findOrFail(2);
        $this->assertFalse($admin->subscribe_newsletter);
        // token verifikasi & OTP lama (plaintext) dibuang
        $this->assertNull($admin->verification_token);
        $this->assertNull($admin->reset_otp);

        $deleted = User::withTrashed()->findOrFail(3);
        $this->assertTrue($deleted->trashed());
        $this->assertSame('user', $deleted->role->value); // role tak dikenal -> user
        $this->assertNull(User::find(3));

        $this->assertTrue(Project::withTrashed()->findOrFail(11)->trashed());
        $this->assertNull(PricingPlan::where('tier', 'enterprise')->first()->max_price);
        $this->assertFalse(PricingPlan::where('tier', 'enterprise')->first()->is_active);
        $this->assertSame(['Desain responsif', 'SEO dasar'], PricingPlan::where('tier', 'starter')->first()->features->pluck('feature')->all());
    }

    public function test_pembayaran_lunas_tanpa_paid_at_memakai_updated_at(): void
    {
        $this->import()->assertSuccessful();

        $paid = Payment::findOrFail(100);
        $this->assertSame(PaymentStatus::Paid, $paid->status);
        $this->assertSame('2026-10-21 12:00:00', $paid->paid_at->format('Y-m-d H:i:s')); // 05:00 UTC
        $this->assertSame(1500000.0, (float) Payment::sum('amount'));
    }

    public function test_berkas_lampiran_disalin_dan_yang_hilang_dilaporkan(): void
    {
        $this->import()->expectsOutputToContain('hilang.png')->assertSuccessful();

        Storage::disk('local')->assertExists('attachments/1/brief.pdf');
        $this->assertSame('isi-pdf!', Storage::disk('local')->get('attachments/1/brief.pdf'));
        Storage::disk('local')->assertMissing('attachments/1/hilang.png');
    }

    public function test_dry_run_tidak_menyimpan_apa_pun(): void
    {
        $this->import(['--dry-run' => true])->expectsOutputToContain('Dry run selesai')->assertSuccessful();

        $this->assertSame(0, User::withTrashed()->count());
        $this->assertSame(0, Payment::count());
        Storage::disk('local')->assertMissing('attachments/1/brief.pdf');
    }

    public function test_menolak_tabel_tujuan_yang_sudah_berisi(): void
    {
        User::factory()->create();

        $this->import()->expectsOutputToContain('sudah berisi data')->assertFailed();
        $this->assertSame(1, User::count());

        // --update menimpa baris ber-ID sama (user factory ber-ID 1 digantikan "demo")
        $this->import(['--update' => true])->assertSuccessful();
        $this->assertSame(3, User::withTrashed()->count());
        $this->assertSame('demo', User::findOrFail(1)->username);
    }

    public function test_dapat_dijalankan_ulang_dengan_update(): void
    {
        $this->import()->assertSuccessful();
        $this->import(['--update' => true])->assertSuccessful();

        $this->assertSame(3, User::withTrashed()->count());
        $this->assertSame(2, Payment::count());
    }

    public function test_data_bermasalah_membatalkan_semuanya(): void
    {
        $this->csv('payments', [
            ['id', 'project_id', 'user_id', 'order_id', 'amount', 'status'],
            ['100', '10', '1', 'WEBKITA-10-1', '750000', 'paid'],
            ['101', '999', '1', 'WEBKITA-999-1', '100', 'paid'],         // proyek tidak ada
            ['102', '10', '1', 'WEBKITA-10-3', 'abc', 'paid'],           // nominal bukan angka
            ['103', '10', '1', 'WEBKITA-10-4', '100', 'aneh'],           // status tidak dikenal
            ['104', '10', '1', 'WEBKITA-10-1', '100', 'paid'],           // order_id duplikat
        ]);

        $this->import()->expectsOutputToContain('baris bermasalah')->assertFailed();
        $this->assertSame(0, User::withTrashed()->count());
        $this->assertSame(0, Payment::count());
    }

    public function test_skip_invalid_mengimpor_baris_yang_sehat(): void
    {
        $this->csv('payments', [
            ['id', 'project_id', 'user_id', 'order_id', 'amount', 'status'],
            ['100', '10', '1', 'WEBKITA-10-1', '750000', 'paid'],
            ['101', '999', '1', 'WEBKITA-999-1', '100', 'paid'],
        ]);

        $this->import(['--skip-invalid' => true])->assertSuccessful();
        $this->assertSame(1, Payment::count());
        $this->assertSame(3, User::withTrashed()->count());
    }

    public function test_berkas_wajib_tidak_ada(): void
    {
        File::delete("{$this->dir}/users.csv");

        $this->import()->expectsOutputToContain('users.csv tidak ditemukan')->assertFailed();
    }

    public function test_path_traversal_pada_lampiran_ditolak(): void
    {
        $this->csv('project_attachments', [
            ['id', 'project_id', 'file_name', 'file_size', 'file_type', 'storage_path'],
            ['1', '10', 'x.txt', '1', 'text/plain', '../../etc/passwd'],
        ]);

        $this->import()->assertSuccessful();
        $this->assertSame(1, ProjectAttachment::count());
        Storage::disk('local')->assertDirectoryEmpty('/');
    }
}
