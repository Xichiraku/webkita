<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\PlanFeature;
use App\Models\PricingPlan;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_relasi_user_project_payment(): void
    {
        $project = Project::factory()->create();
        $payment = Payment::factory()->for($project)->create();

        $this->assertTrue($project->user->is($payment->user));
        $this->assertSame(ProjectStatus::Pending, $project->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertCount(1, $project->user->projects);
    }

    public function test_mass_assignment_tidak_bisa_mengubah_role(): void
    {
        $user = User::create([
            'username' => 'x', 'email' => 'x@x.test', 'password' => 'secret123',
            'role' => 'admin', 'is_aktif' => 'Y',
        ]);

        $this->assertSame(Role::User, $user->fresh()->role);
        $this->assertSame('N', $user->fresh()->is_aktif);
    }

    public function test_seeder_menghasilkan_data_demo(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class); // idempoten

        $this->assertSame(2, User::count());
        $this->assertSame(Role::Admin, User::where('email', 'admin@webkita.local')->first()->role);
        $this->assertSame(4, PricingPlan::count());
        $this->assertNull(PricingPlan::where('tier', 'enterprise')->first()->max_price);
        $this->assertSame(15, PlanFeature::count());
        $this->assertSame(5, Project::count());
        $this->assertSame(5, Payment::count());
        $this->assertNotNull(Payment::where('order_id', 'DEMO-WEBKITA-001')->first()->paid_at);

        // Hash bcrypt $2b$ dari Supabase harus tetap valid di Laravel.
        $demo = User::where('email', 'demo@webkita.local')->first();
        $admin = User::where('email', 'admin@webkita.local')->first();
        $this->assertStringStartsWith('$2y$12$', $demo->password);
        $this->assertTrue(Hash::check('Demo1234!', $demo->password));
        $this->assertTrue(Hash::check('Admin1234!', $admin->password));
    }

    public function test_hapus_paket_ikut_menghapus_fitur(): void
    {
        $this->seed(DatabaseSeeder::class);
        PricingPlan::where('tier', 'starter')->first()->delete();

        $this->assertSame(12, PlanFeature::count());
    }
}
