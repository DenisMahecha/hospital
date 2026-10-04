<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('system_settings');
    }

    public function down(): void
    {
        // System settings were intentionally removed.
    }
};