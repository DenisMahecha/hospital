<?php

namespace Tests\Feature;

use App\Models\Drug;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HmsPhaseTwoFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_lab_pharmacy_and_reports_routes_work(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user);

        $this->get('/lab')->assertOk();
        $this->get('/pharmacy')->assertOk();
        $this->get('/reports')->assertOk();

        $patient = Patient::create([
            'patient_number' => 'PT-8801',
            'first_name' => 'Grace',
            'last_name' => 'Mkwawa',
            'gender' => 'female',
            'date_of_birth' => '1992-11-22',
            'phone' => '+255700111222',
            'address' => 'Morogoro',
            'next_of_kin_name' => 'Sam Mkwawa',
            'next_of_kin_phone' => '+255711222333',
            'insurance_provider' => 'NHIF',
        ]);

        $labRequest = LabRequest::create([
            'request_number' => 'LAB-1001',
            'patient_id' => $patient->id,
            'requested_by' => $user->id,
            'test_name' => 'CBC',
            'status' => 'pending',
        ]);

        $drug = Drug::create([
            'name' => 'Paracetamol',
            'category' => 'analgesic',
            'form' => 'tablet',
            'unit_price' => 250,
            'stock_quantity' => 120,
            'expiry_date' => '2027-12-31',
            'status' => 'active',
        ]);

        StockMovement::create([
            'drug_id' => $drug->id,
            'movement_type' => 'in',
            'quantity' => 50,
            'reference' => 'Initial stock',
            'notes' => 'Opening inventory',
        ]);

        $this->assertDatabaseHas('lab_requests', ['request_number' => 'LAB-1001']);
        $this->assertDatabaseHas('drugs', ['name' => 'Paracetamol']);
        $this->assertDatabaseHas('stock_movements', ['reference' => 'Initial stock']);
    }
}
