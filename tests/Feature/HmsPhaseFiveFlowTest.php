<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BackupLog;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\PatientPortalAccount;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HmsPhaseFiveFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_and_portal_routes_and_records_work(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Inventory Clerk',
            'email' => 'clerk@example.com',
            'username' => 'inventoryclerk',
            'password' => 'secret123',
            'phone' => '+255712345678',
            'is_active' => true,
        ]);
        $user->assignRole('super-admin');
        $this->actingAs($user);

        $this->get('/inventory')->assertOk();
        $this->get('/portal')->assertOk();

        $patient = Patient::create([
            'patient_number' => 'PT-9003',
            'first_name' => 'Zawadi',
            'last_name' => 'Mwalimu',
            'gender' => 'female',
            'date_of_birth' => '1998-05-15',
            'phone' => '+255700010011',
            'address' => 'Dodoma',
            'next_of_kin_name' => 'John Mwalimu',
            'next_of_kin_phone' => '+255700010012',
            'insurance_provider' => 'Jubilee',
        ]);

        $supplier = Supplier::create([
            'name' => 'Medline Supplies',
            'contact_person' => 'Jane Mushi',
            'phone' => '+255688000111',
            'email' => 'info@medline.co.tz',
            'address' => 'Dar es Salaam',
            'tax_number' => 'TAX-8901',
            'status' => 'active',
        ]);

        $item = InventoryItem::create([
            'item_code' => 'INV-1001',
            'name' => 'Paracetamol 500mg',
            'category' => 'medicine',
            'unit' => 'box',
            'reorder_level' => 20,
            'current_stock' => 75,
            'status' => 'active',
            'supplier_id' => $supplier->id,
        ]);

        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-1001',
            'supplier_id' => $supplier->id,
            'ordered_by' => $user->id,
            'order_date' => '2026-09-19',
            'expected_date' => '2026-09-27',
            'status' => 'approved',
            'total_amount' => 220000,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'inventory_item_id' => $item->id,
            'quantity' => 25,
            'unit_cost' => 8800,
            'total_cost' => 220000,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Low stock alert',
            'message' => 'Paracetamol stock is below reorder level',
            'type' => 'warning',
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'user_type' => 'App\\Models\\User',
            'action' => 'create',
            'module_name' => 'inventory',
            'model_type' => 'App\\Models\\PurchaseOrder',
            'model_id' => $purchaseOrder->id,
            'description' => 'Purchase order approved',
            'ip_address' => '127.0.0.1',
        ]);

        PatientPortalAccount::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'portal_username' => 'zawadi.mwalimu',
            'status' => 'active',
        ]);

        BackupLog::create([
            'backup_name' => 'daily-db-backup',
            'storage_path' => '/storage/backups/daily-db-backup.sql',
            'file_size' => 204800,
            'status' => 'success',
        ]);

        $this->assertDatabaseHas('suppliers', ['name' => 'Medline Supplies']);
        $this->assertDatabaseHas('inventory_items', ['item_code' => 'INV-1001']);
        $this->assertDatabaseHas('purchase_orders', ['po_number' => 'PO-1001']);
        $this->assertDatabaseHas('purchase_order_items', ['quantity' => 25]);
        $this->assertDatabaseHas('notifications', ['title' => 'Low stock alert']);
        $this->assertDatabaseHas('audit_logs', ['module_name' => 'inventory']);
        $this->assertDatabaseHas('patient_portal_accounts', ['portal_username' => 'zawadi.mwalimu']);
        $this->assertDatabaseHas('backup_logs', ['backup_name' => 'daily-db-backup']);
    }
}
