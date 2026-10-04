<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BackupLog;
use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HmsOperationsFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_notifications_audit_and_backup_workspaces_are_available(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user);

        $this->get('/suppliers')->assertOk();
        $this->get('/purchase-orders')->assertOk();
        $this->get('/notifications')->assertOk();
        $this->get('/audit-logs')->assertOk();
        $this->get('/backups')->assertOk();
        config(['backup.driver' => 'log']);
        $this->post('/backups')->assertRedirect('/backups');

        $supplier = Supplier::create(['name' => 'Careline Medical', 'status' => 'active']);
        $item = InventoryItem::create(['item_code' => 'OPS-1001', 'name' => 'Surgical gloves', 'category' => 'consumable', 'unit' => 'box', 'current_stock' => 5, 'reorder_level' => 10, 'supplier_id' => $supplier->id]);
        $order = PurchaseOrder::create(['po_number' => 'PO-OPS-1', 'supplier_id' => $supplier->id, 'ordered_by' => $user->id, 'status' => 'approved', 'total_amount' => 150000]);
        PurchaseOrderItem::create(['purchase_order_id' => $order->id, 'inventory_item_id' => $item->id, 'quantity' => 10, 'unit_cost' => 15000, 'total_cost' => 150000]);

        $this->post("/purchase-orders/{$order->id}/receive")->assertRedirect('/purchase-orders');
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'current_stock' => 15]);
        $this->assertDatabaseHas('inventory_movements', ['inventory_item_id' => $item->id, 'movement_type' => 'in', 'quantity' => 10]);

        $draft = PurchaseOrder::create(['po_number' => 'PO-DRAFT-1', 'supplier_id' => $supplier->id, 'ordered_by' => $user->id, 'status' => 'draft', 'total_amount' => 150000]);
        PurchaseOrderItem::create(['purchase_order_id' => $draft->id, 'inventory_item_id' => $item->id, 'quantity' => 3, 'unit_cost' => 15000, 'total_cost' => 45000]);
        $this->post("/purchase-orders/{$draft->id}/receive")->assertStatus(422);
        $this->assertDatabaseHas('purchase_orders', ['id' => $draft->id, 'status' => 'draft']);

        $notification = Notification::create(['user_id' => $user->id, 'title' => 'Stock received', 'message' => 'Purchase order received', 'type' => 'success']);
        $this->post("/notifications/{$notification->id}/read")->assertRedirect('/notifications');
        $this->assertNotNull($notification->fresh()->read_at);

        AuditLog::create(['user_id' => $user->id, 'action' => 'view', 'module_name' => 'audit', 'description' => 'Audit log opened']);
        BackupLog::create(['backup_name' => 'nightly', 'storage_path' => 'backups/nightly.sql', 'status' => 'success']);
        $this->assertDatabaseHas('audit_logs', ['module_name' => 'audit']);
        $this->assertDatabaseHas('backup_logs', ['backup_name' => 'nightly']);
    }

    public function test_approved_medicine_purchase_order_receives_into_drug_batch_stock(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $supplier = Supplier::create(['name' => 'Pharma Supplier', 'status' => 'active']);
        $drug = Drug::create(['name' => 'Azithromycin', 'stock_quantity' => 0, 'status' => 'active']);
        $order = PurchaseOrder::create(['po_number' => 'PO-MED-001', 'supplier_id' => $supplier->id, 'ordered_by' => $user->id, 'status' => 'approved', 'total_amount' => 50000]);
        PurchaseOrderItem::create(['purchase_order_id' => $order->id, 'drug_id' => $drug->id, 'batch_number' => 'AZ-001', 'expiry_date' => now()->addYear(), 'quantity' => 10, 'unit_cost' => 5000, 'total_cost' => 50000]);

        $this->actingAs($user)->post("/purchase-orders/{$order->id}/receive")->assertRedirect('/purchase-orders');

        $this->assertDatabaseHas('drugs', ['id' => $drug->id, 'stock_quantity' => 10]);
        $this->assertDatabaseHas('drug_batches', ['drug_id' => $drug->id, 'batch_number' => 'AZ-001', 'quantity' => 10]);
        $this->assertDatabaseHas('stock_movements', ['drug_id' => $drug->id, 'movement_type' => 'in', 'quantity' => 10, 'reference' => 'PO-MED-001']);
    }
}
