<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drug_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('drug_id')->constrained('drugs')->cascadeOnDelete();
            $table->string('batch_number');
            $table->unsignedInteger('quantity')->default(0);
            $table->date('expiry_date');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['drug_id', 'batch_number']);
            $table->index(['expiry_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_batches');
    }
};