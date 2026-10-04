<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wards', function (Blueprint $table) {
            if (!Schema::hasColumn('wards', 'zone')) {
                $table->string('zone')->default('General')->after('name');
            }

            if (!Schema::hasColumn('wards', 'ward_type')) {
                $table->string('ward_type')->default('general')->after('zone');
            }

            if (!Schema::hasColumn('wards', 'gender_group')) {
                $table->string('gender_group')->default('mixed')->after('ward_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wards', function (Blueprint $table) {
            if (Schema::hasColumn('wards', 'gender_group')) {
                $table->dropColumn('gender_group');
            }

            if (Schema::hasColumn('wards', 'ward_type')) {
                $table->dropColumn('ward_type');
            }

            if (Schema::hasColumn('wards', 'zone')) {
                $table->dropColumn('zone');
            }
        });
    }
};
