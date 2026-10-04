<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('is_active');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $table->boolean('two_factor_enabled')->default(false)->after('locked_until');
            $table->string('two_factor_code_hash')->nullable()->after('two_factor_enabled');
            $table->timestamp('two_factor_expires_at')->nullable()->after('two_factor_code_hash');
        });
        Schema::table('patients', function (Blueprint $table): void {
            $table->index('phone');
            $table->index('patient_number');
        });
        Schema::table('drugs', function (Blueprint $table): void {
            $table->index('name');
        });
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->index('item_code');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['failed_login_attempts', 'locked_until', 'two_factor_enabled', 'two_factor_code_hash', 'two_factor_expires_at']);
        });
        Schema::table('patients', fn (Blueprint $table) => $table->dropIndex(['phone']));
        Schema::table('patients', fn (Blueprint $table) => $table->dropIndex(['patient_number']));
        Schema::table('drugs', fn (Blueprint $table) => $table->dropIndex(['name']));
        Schema::table('inventory_items', fn (Blueprint $table) => $table->dropIndex(['item_code']));
        Schema::table('inventory_items', fn (Blueprint $table) => $table->dropIndex(['name']));
    }
};