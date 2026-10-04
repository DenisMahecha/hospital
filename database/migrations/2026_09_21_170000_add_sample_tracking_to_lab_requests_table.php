<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_requests', function (Blueprint $table): void {
            $table->foreignId('collected_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('collected_at')->nullable()->after('collected_by');
            $table->foreignId('processing_started_by')->nullable()->after('collected_at')->constrained('users')->nullOnDelete();
            $table->timestamp('processing_started_at')->nullable()->after('processing_started_by');
            $table->foreignId('rejected_by')->nullable()->after('processing_started_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('lab_requests', function (Blueprint $table): void {
            $table->dropForeign(['collected_by']);
            $table->dropForeign(['processing_started_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn(['collected_by', 'collected_at', 'processing_started_by', 'processing_started_at', 'rejected_by', 'rejected_at', 'rejection_reason']);
        });
    }
};
