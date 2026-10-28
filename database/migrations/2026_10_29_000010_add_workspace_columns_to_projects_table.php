<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('developer_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('developer_id');
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');
            $table->timestamp('completed_at')->nullable()->after('invoice_issued_at');
            $table->unsignedTinyInteger('revision_limit')->nullable()->default(3)->after('completed_at');
            $table->unsignedTinyInteger('revisions_used')->default(0)->after('revision_limit');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('developer_id');
            $table->dropColumn(['assigned_at', 'progress_percent', 'completed_at', 'revision_limit', 'revisions_used']);
        });
    }
};
