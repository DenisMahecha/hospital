<?php

namespace Tests\Feature;

use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StorekeeperWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function storekeeper(): User
    {
        Role::firstOrCreate(['name' => 'store-keeper', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('store-keeper');
        return $user;
    }

    public function test_storekeeper_can_access_inventory_suppliers_and_purchase_orders(): void
    {
        $user = $this->storekeeper();
        InventoryItem::create(['item_code' => 'SEARCH-1', 'name' => 'Gloves', 'unit' => 'box', 'status' => 'active']);
        $this->actingAs($user)->get('/inventory')->assertOk();
        $this->actingAs($user)->get('/inventory?q=gloves')->assertOk()->assertSee('Gloves');
        $this->actingAs($user)->get('/suppliers')->assertOk();
        $this->actingAs($user)->get('/purchase-orders')->assertOk();

        $supplier = Supplier::create(['name' => 'Directory Supplier', 'status' => 'active']);
        $this->actingAs($user)->patch("/suppliers/{$supplier->id}", ['name' => 'Inactive Directory Supplier', 'status' => 'inactive'])->assertRedirect('/suppliers');
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'status' => 'inactive']);
    }

    public function test_purchase_order_requires_one_active_target_per_line(): void
    {
        $user = $this->storekeeper();
        $supplier = Supplier::create(['name' => 'Active supplier', 'status' => 'active']);
        $item = InventoryItem::create(['item_code' => 'ITEM-1', 'name' => 'Gloves', 'unit' => 'box', 'status' => 'active']);
        $drug = Drug::create(['name' => 'Medicine', 'status' => 'active']);

        $payload = ['supplier_id' => $supplier->id, 'items' => [['inventory_item_id' => $item->id, 'drug_id' => $drug->id, 'quantity' => 1, 'unit_cost' => 100]]];
        $this->actingAs($user)->post('/purchase-orders', $payload)->assertStatus(422);
        $payload['items'][0]['drug_id'] = null;
        $this->actingAs($user)->post('/purchase-orders', $payload)->assertRedirect('/purchase-orders');
        $this->assertDatabaseHas('audit_logs', ['module_name' => 'purchase_orders', 'action' => 'create']);
    }

    public function test_inactive_procurement_records_are_rejected(): void
    {
        $user = $this->storekeeper();
        $supplier = Supplier::create(['name' => 'Inactive supplier', 'status' => 'inactive']);
        $item = InventoryItem::create(['item_code' => 'ITEM-2', 'name' => 'Inactive item', 'unit' => 'box', 'status' => 'inactive']);

        $this->actingAs($user)->post('/purchase-orders', ['supplier_id' => $supplier->id, 'items' => [['inventory_item_id' => $item->id, 'quantity' => 1, 'unit_cost' => 100]]])->assertStatus(422);
    }

    public function test_existing_batch_with_different_expiry_is_rejected_on_receipt(): void
    {
        $user = $this->storekeeper();
        $supplier = Supplier::create(['name' => 'Pharma supplier', 'status' => 'active']);
        $drug = Drug::create(['name' => 'Test medicine', 'stock_quantity' => 0, 'status' => 'active']);
        DrugBatch::create(['drug_id' => $drug->id, 'batch_number' => 'B-1', 'quantity' => 5, 'expiry_date' => now()->addMonths(3), 'status' => 'active']);
        $order = PurchaseOrder::create(['po_number' => 'PO-EXPIRY-1', 'supplier_id' => $supplier->id, 'ordered_by' => $user->id, 'status' => 'approved', 'total_amount' => 100]);
        PurchaseOrderItem::create(['purchase_order_id' => $order->id, 'drug_id' => $drug->id, 'batch_number' => 'B-1', 'expiry_date' => now()->addMonths(6), 'quantity' => 1, 'unit_cost' => 100, 'total_cost' => 100]);

        $this->actingAs($user)->post("/purchase-orders/{$order->id}/receive")->assertStatus(422);
        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'approved']);
    }

    public function test_storekeeper_can_manage_inventory_and_record_movements(): void
    {
        $user = $this->storekeeper();
        $this->actingAs($user)->post('/inventory', ['item_code' => 'ITEM-NEW', 'name' => 'Syringes', 'category' => 'Consumables', 'unit' => 'box', 'reorder_level' => 10])->assertRedirect();
        $item = InventoryItem::where('item_code', 'ITEM-NEW')->firstOrFail();
        $this->actingAs($user)->patch("/inventory/{$item->id}", ['item_code' => 'ITEM-NEW', 'name' => 'Syringes 5ml', 'category' => 'Consumables', 'unit' => 'box', 'reorder_level' => 5, 'status' => 'active'])->assertRedirect();
        $this->actingAs($user)->post("/inventory/{$item->id}/adjust", ['movement_type' => 'in', 'quantity' => 20, 'remarks' => 'Opening stock'])->assertRedirect();
        $this->actingAs($user)->post("/inventory/{$item->id}/adjust", ['movement_type' => 'damaged', 'quantity' => 16, 'reference' => 'DAM-INV-1', 'remarks' => 'Damaged boxes'])->assertRedirect();
        $this->actingAs($user)->get('/inventory/history')->assertOk()->assertSee('Damaged boxes');

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'name' => 'Syringes 5ml', 'current_stock' => 4]);
        $this->assertDatabaseHas('inventory_movements', ['inventory_item_id' => $item->id, 'movement_type' => 'damaged', 'quantity' => 16]);
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => 'inventory_stock']);
    }

    public function test_storekeeper_can_review_low_stock_alerts(): void
    {
        $user = $this->storekeeper();
        $item = InventoryItem::create(['item_code' => 'ITEM-LOW', 'name' => 'Surgical masks', 'unit' => 'box', 'current_stock' => 3, 'reorder_level' => 12, 'status' => 'active']);

        $this->actingAs($user)
            ->get('/inventory/alerts')
            ->assertOk()
            ->assertSee('Low stock alerts')
            ->assertSee('Surgical masks')
            ->assertSee('12');

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'current_stock' => 3, 'reorder_level' => 12]);
    }

    public function test_storekeeper_cannot_remove_more_than_available_inventory(): void
    {
        $user = $this->storekeeper();
        $item = InventoryItem::create(['item_code' => 'ITEM-LIMIT', 'name' => 'Gloves', 'unit' => 'box', 'current_stock' => 2, 'status' => 'active']);

        $this->actingAs($user)->post("/inventory/{$item->id}/adjust", ['movement_type' => 'out', 'quantity' => 3, 'remarks' => 'Invalid issue'])->assertStatus(422);
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'current_stock' => 2]);
    }
}
