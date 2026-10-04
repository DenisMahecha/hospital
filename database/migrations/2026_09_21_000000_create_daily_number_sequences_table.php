<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 50);
            $table->string('sequence_date', 8);
            $table->unsignedInteger('next_value')->default(1);
            $table->timestamps();
            $table->unique(['scope', 'sequence_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_number_sequences');
    }
};
