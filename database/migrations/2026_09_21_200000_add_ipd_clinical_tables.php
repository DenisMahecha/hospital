<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_vitals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->string('blood_pressure')->nullable();
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('oxygen_saturation', 5, 2)->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->string('consciousness')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['admission_id', 'recorded_at']);
        });

        Schema::create('medication_administrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->foreignId('prescription_id')->nullable()->constrained('prescriptions')->nullOnDelete();
            $table->string('medication_name');
            $table->string('dose')->nullable();
            $table->string('route')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('administered_at')->nullable();
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->foreignId('administered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['admission_id', 'scheduled_at']);
        });

        Schema::create('admission_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->foreignId('from_ward_id')->nullable()->constrained('wards')->nullOnDelete();
            $table->foreignId('to_ward_id')->constrained('wards')->restrictOnDelete();
            $table->text('reason');
            $table->foreignId('transferred_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('transferred_at');
            $table->timestamps();
        });

        Schema::create('ward_rounds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->date('round_date');
            $table->text('note');
            $table->text('plan')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['round_date', 'admission_id']);
        });

        Schema::table('admissions', function (Blueprint $table): void {
            $table->text('discharge_summary')->nullable()->after('notes');
            $table->text('discharge_condition')->nullable()->after('discharge_summary');
            $table->text('discharge_medications')->nullable()->after('discharge_condition');
            $table->date('follow_up_date')->nullable()->after('discharge_medications');
            $table->foreignId('discharged_by')->nullable()->after('follow_up_date')->constrained('users')->nullOnDelete();
            $table->timestamp('discharged_at')->nullable()->after('discharged_by');
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table): void {
            $table->dropForeign(['discharged_by']);
            $table->dropColumn(['discharge_summary', 'discharge_condition', 'discharge_medications', 'follow_up_date', 'discharged_by', 'discharged_at']);
        });
        Schema::dropIfExists('ward_rounds');
        Schema::dropIfExists('admission_transfers');
        Schema::dropIfExists('medication_administrations');
        Schema::dropIfExists('admission_vitals');
    }
};