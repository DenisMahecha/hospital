<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->foreignId('dispensed_drug_id')->nullable()->after('visit_id')->constrained('drugs')->nullOnDelete();
            $table->unsignedInteger('quantity_prescribed')->nullable()->after('duration');
            $table->unsignedInteger('quantity_dispensed')->default(0)->after('quantity_prescribed');
            $table->timestamp('dispensed_at')->nullable()->after('quantity_dispensed');
            $table->foreignId('dispensed_by')->nullable()->after('dispensed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->dropForeign(['dispensed_drug_id']);
            $table->dropForeign(['dispensed_by']);
            $table->dropColumn(['dispensed_drug_id', 'quantity_prescribed', 'quantity_dispensed', 'dispensed_at', 'dispensed_by']);
        });
    }
};