<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position');
            $table->date('due_date')->nullable();
            $table->string('status', 12)->default('planned');
            $table->unsignedTinyInteger('percent')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'position']);
        });

        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('milestone_id')->constrained('project_milestones');
            $table->foreignId('user_id')->constrained('users');
            $table->string('type', 10);
            $table->string('title', 100)->nullable();
            $table->text('note');
            $table->unsignedTinyInteger('percent');
            $table->json('links');
            $table->string('review_status', 20)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'id']);
            $table->index(['milestone_id', 'id']);
        });

        Schema::table('project_attachments', function (Blueprint $table) {
            $table->foreignId('update_id')->nullable()->after('project_id')->constrained('project_updates')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->after('storage_path')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('update_id');
            $table->dropConstrainedForeignId('uploaded_by');
        });
        Schema::dropIfExists('project_updates');
        Schema::dropIfExists('project_milestones');
    }
};
