<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('tier');
            $table->decimal('min_price', 15, 2);
            $table->decimal('max_price', 15, 2)->nullable();
            $table->string('price_range');
            $table->string('timeline')->nullable();
            $table->string('badge')->nullable();
            $table->text('description')->nullable();
            $table->text('upgrade_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        foreach (['plan_features', 'plan_not_included'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('plan_id')->constrained('pricing_plans')->cascadeOnDelete();
                $table->string('feature');
                $table->integer('sort_order')->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_not_included');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('pricing_plans');
    }
};
