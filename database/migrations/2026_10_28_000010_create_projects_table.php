<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('plan_title')->default('');
            $table->string('price_range')->default('');
            $table->string('plan_price_range')->nullable();
            $table->string('project_title');
            $table->string('category');
            $table->text('description');
            $table->text('skills');
            $table->string('contact_name');
            $table->string('contact_phone');
            $table->text('additional_notes')->nullable();
            $table->string('status')->default('pending')->index();
            $table->text('reject_reason')->nullable();
            $table->string('invoice_number')->nullable();
            $table->timestamp('invoice_issued_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
