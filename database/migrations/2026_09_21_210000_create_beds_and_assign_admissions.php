<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ward_id')->constrained('wards')->cascadeOnDelete();
            $table->string('bed_number');
            $table->string('room')->nullable();
            $table->string('status')->default('available');
            $table->timestamps();
            $table->unique(['ward_id', 'bed_number']);
        });
        Schema::table('admissions', function (Blueprint $table): void {
            $table->foreignId('bed_id')->nullable()->after('ward_id')->constrained('beds')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table): void {
            $table->dropForeign(['bed_id']);
            $table->dropColumn('bed_id');
        });
        Schema::dropIfExists('beds');
    }
};