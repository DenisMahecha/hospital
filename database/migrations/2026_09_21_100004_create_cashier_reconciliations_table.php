<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->date('business_date');
            $table->decimal('system_total', 12, 2);
            $table->decimal('declared_total', 12, 2);
            $table->decimal('variance', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['cashier_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_reconciliations');
    }
};