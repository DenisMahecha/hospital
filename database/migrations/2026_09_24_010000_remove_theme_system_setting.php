<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->where('key', 'theme')->delete();
    }

    public function down(): void
    {
        // Theme settings are intentionally no longer supported.
    }
};