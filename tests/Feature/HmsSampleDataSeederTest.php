<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HmsSampleDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_demo_users_and_records(): void
    {
        $this->seed();
        $this->assertDatabaseHas('users', ['email' => 'doctor@hospital.test']);
        $this->assertDatabaseHas('users', ['email' => 'patient@hospital.test']);
        $this->assertDatabaseHas('patients', ['patient_number' => 'PT-DEMO-001']);
        $this->assertDatabaseHas('appointments', ['appointment_number' => 'APT-DEMO-001']);
        $this->assertDatabaseHas('inventory_items', ['item_code' => 'INV-DEMO-001']);
        $this->assertDatabaseHas('purchase_orders', ['po_number' => 'PO-DEMO-001']);
        $this->assertGreaterThan(0, User::count());
        $this->assertGreaterThan(0, Patient::count());
        $this->assertGreaterThan(0, Appointment::count());
        $this->assertGreaterThan(0, InventoryItem::count());
        $this->assertGreaterThan(0, PurchaseOrder::count());
    }
}
