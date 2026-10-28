<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('update_id')->nullable()->constrained('project_updates')->nullOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index(['project_id', 'id']);
            $table->index('update_id');
        });

        Schema::create('project_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->constrained('project_milestones');
            $table->foreignId('update_id')->constrained('project_updates');
            $table->unsignedTinyInteger('number');
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users');
            $table->string('status', 16)->default('open');
            $table->foreignId('resolved_by_update_id')->nullable()->constrained('project_updates')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();

            $table->unique(['project_id', 'number']);
            $table->index(['milestone_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_revisions');
        Schema::dropIfExists('project_comments');
    }
};
