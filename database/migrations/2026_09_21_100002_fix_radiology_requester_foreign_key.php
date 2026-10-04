<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radiology_requests', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('radiology_requests', function (Blueprint $table) {
            $table->dropForeign(['requested_by']);
            $table->foreign('requested_by')->references('id')->on('employees')->nullOnDelete();
        });
    }
};