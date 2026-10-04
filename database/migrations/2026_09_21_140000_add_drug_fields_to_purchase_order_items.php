<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->dropForeign(['inventory_item_id']);
            $table->foreignId('inventory_item_id')->nullable()->change();
            $table->foreignId('drug_id')->nullable()->after('inventory_item_id')->constrained('drugs')->nullOnDelete();
            $table->string('batch_number')->nullable()->after('drug_id');
            $table->date('expiry_date')->nullable()->after('batch_number');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->dropForeign(['drug_id']);
            $table->dropColumn(['drug_id', 'batch_number', 'expiry_date']);
            $table->foreignId('inventory_item_id')->nullable(false)->change();
            $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->cascadeOnDelete();
        });
    }
};