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

class HmsPhaseTwoFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lab_pharmacy_and_stock_foundation_are_available(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $patient = Patient::create([
            'patient_number' => 'PT-FOUNDATION-1',
            'first_name' => 'Foundation',
            'last_name' => 'Patient',
            'gender' => 'other',
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

        $this->actingAs($user)->get('/lab')->assertOk();
        $this->actingAs($user)->get('/pharmacy')->assertOk();
        $this->actingAs($user)->get('/reports')->assertOk();
    }
}
