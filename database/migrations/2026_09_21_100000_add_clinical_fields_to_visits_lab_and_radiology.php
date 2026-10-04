<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->text('diagnosis')->nullable()->after('complaint');
            $table->string('icd10_code')->nullable()->after('diagnosis');
            $table->text('referral_notes')->nullable()->after('notes');
            $table->date('follow_up_date')->nullable()->after('referral_notes');
        });

        Schema::table('lab_requests', function (Blueprint $table) {
            $table->text('result')->nullable()->after('notes');
            $table->string('reference_range')->nullable()->after('result');
            $table->foreignId('completed_by')->nullable()->after('reference_range')->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('completed_by');
        });

        Schema::table('radiology_requests', function (Blueprint $table) {
            $table->text('report')->nullable()->after('status');
            $table->string('image_path')->nullable()->after('report');
            $table->foreignId('reported_by')->nullable()->after('image_path')->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at')->nullable()->after('reported_by');
        });
    }

    public function down(): void
    {
        Schema::table('radiology_requests', function (Blueprint $table) {
            $table->dropForeign(['reported_by']);
            $table->dropColumn(['report', 'image_path', 'reported_by', 'reported_at']);
        });

        Schema::table('lab_requests', function (Blueprint $table) {
            $table->dropForeign(['completed_by']);
            $table->dropColumn(['result', 'reference_range', 'completed_by', 'completed_at']);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['diagnosis', 'icd10_code', 'referral_notes', 'follow_up_date']);
        });
    }
};