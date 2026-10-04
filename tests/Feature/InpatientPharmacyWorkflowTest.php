<?php

namespace Tests\Feature;

use App\Models\Drug;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\DrugBatch;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InpatientPharmacyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinical_staff_admit_discharge_and_pharmacist_dispenses_stock(): void
    {
        foreach (['nurse', 'pharmacist'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $nurse = User::factory()->create();
        $nurse->assignRole('nurse');
        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('pharmacist');
        $patient = Patient::create(['patient_number' => 'PAT-IPD-001', 'first_name' => 'Juma', 'last_name' => 'Kassim']);
        $ward = Ward::create(['name' => 'Medical Ward', 'capacity' => 20, 'status' => 'active']);
        $drug = Drug::create(['name' => 'Paracetamol', 'stock_quantity' => 20, 'status' => 'active']);

        $this->actingAs($nurse)->post('/admissions', ['patient_id' => $patient->id, 'ward_id' => $ward->id, 'admission_number' => 'ADM-IPD-001'])->assertRedirect();
        $admission = $patient->admissions()->first();
        $this->actingAs($nurse)->post("/admissions/{$admission->id}/discharge", ['discharge_summary' => 'Discharged after observation.', 'discharge_condition' => 'Stable'])->assertRedirect();
        $this->actingAs($pharmacist)->post('/pharmacy/dispense', ['drug_id' => $drug->id, 'quantity' => 5, 'reference' => 'RX-IPD-001'])->assertRedirect();

        $this->assertDatabaseHas('admissions', ['admission_number' => 'ADM-'.now()->format('Ymd').'-0001', 'status' => 'discharged']);
        $this->assertDatabaseHas('drugs', ['id' => $drug->id, 'stock_quantity' => 15]);
        $this->assertDatabaseHas('stock_movements', ['drug_id' => $drug->id, 'movement_type' => 'out', 'quantity' => 5]);
    }

    public function test_dispensing_links_prescription_and_rejects_wrong_medicine_or_excess_quantity(): void
    {
        Role::create(['name' => 'pharmacist', 'guard_name' => 'web']);
        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('pharmacist');
        $patient = Patient::create(['patient_number' => 'PAT-PHARM-001', 'first_name' => 'Asha', 'last_name' => 'Musa']);
        $drug = Drug::create(['name' => 'Amoxicillin', 'stock_quantity' => 20, 'status' => 'active']);
        $wrongDrug = Drug::create(['name' => 'Ibuprofen', 'stock_quantity' => 20, 'status' => 'active']);
        $prescription = Prescription::create(['patient_id' => $patient->id, 'prescription_number' => 'RX-PHARM-001', 'medication_name' => 'Amoxicillin', 'quantity_prescribed' => 10, 'quantity_dispensed' => 0, 'status' => 'active']);

        $this->actingAs($pharmacist)->from('/pharmacy')->post('/pharmacy/dispense', ['drug_id' => $wrongDrug->id, 'prescription_id' => $prescription->id, 'quantity' => 5])->assertRedirect('/pharmacy')->assertSessionHasErrors(['drug_id']);
        $this->actingAs($pharmacist)->from('/pharmacy')->post('/pharmacy/dispense', ['drug_id' => $drug->id, 'prescription_id' => $prescription->id, 'quantity' => 11])->assertRedirect('/pharmacy')->assertSessionHasErrors(['quantity']);
        $this->actingAs($pharmacist)->post('/pharmacy/dispense', ['drug_id' => $drug->id, 'prescription_id' => $prescription->id, 'quantity' => 5])->assertRedirect(route('pharmacy.receipt', $prescription));
        $this->actingAs($pharmacist)->get(route('pharmacy.receipt', $prescription))->assertOk()->assertSee('RX-PHARM-001');

        $this->assertDatabaseHas('prescriptions', ['id' => $prescription->id, 'dispensed_drug_id' => $drug->id, 'quantity_dispensed' => 5, 'status' => 'partially_dispensed']);
        $this->assertDatabaseHas('drugs', ['id' => $drug->id, 'stock_quantity' => 15]);
    }

    public function test_dispensing_accepts_equivalent_dosage_format_for_the_same_medicine(): void
    {
        Role::create(['name' => 'pharmacist', 'guard_name' => 'web']);
        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('pharmacist');
        $patient = Patient::create(['patient_number' => 'PAT-PHARM-002', 'first_name' => 'Salma', 'last_name' => 'Asha']);
        $drug = Drug::create(['name' => 'Amoxicillin', 'stock_quantity' => 20, 'status' => 'active']);
        $prescription = Prescription::create(['patient_id' => $patient->id, 'prescription_number' => 'RX-PHARM-002', 'medication_name' => 'Amoxicillin 500mg', 'quantity_prescribed' => 10, 'quantity_dispensed' => 0, 'status' => 'active']);

        $this->actingAs($pharmacist)->post('/pharmacy/dispense', ['drug_id' => $drug->id, 'prescription_id' => $prescription->id, 'quantity' => 3])->assertRedirect(route('pharmacy.receipt', $prescription));

        $this->assertDatabaseHas('prescriptions', ['id' => $prescription->id, 'dispensed_drug_id' => $drug->id, 'quantity_dispensed' => 3, 'status' => 'partially_dispensed']);
    }

    public function test_pharmacist_can_receive_return_and_record_damaged_stock(): void
    {
        Role::create(['name' => 'pharmacist', 'guard_name' => 'web']);
        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('pharmacist');
        $drug = Drug::create(['name' => 'Ceftriaxone', 'stock_quantity' => 10, 'status' => 'active']);

        $this->actingAs($pharmacist)->post('/pharmacy/receive', ['drug_id' => $drug->id, 'quantity' => 20, 'reference' => 'GRN-001', 'expiry_date' => now()->addYear()->toDateString()])->assertRedirect();
        $this->actingAs($pharmacist)->post('/pharmacy/adjust', ['drug_id' => $drug->id, 'movement_type' => 'return', 'quantity' => 2, 'reference' => 'RET-001', 'notes' => 'Patient return'])->assertRedirect();
        $this->actingAs($pharmacist)->post('/pharmacy/adjust', ['drug_id' => $drug->id, 'movement_type' => 'adjustment', 'quantity' => 3, 'reference' => 'DAM-001', 'notes' => 'Damaged packaging'])->assertRedirect();
        $this->actingAs($pharmacist)->post('/pharmacy/adjust', ['drug_id' => $drug->id, 'movement_type' => 'adjustment', 'quantity' => 100, 'reference' => 'DAM-002', 'notes' => 'Too many'])->assertStatus(422);

        $this->assertDatabaseHas('drugs', ['id' => $drug->id, 'stock_quantity' => 29]);
        $this->assertDatabaseHas('stock_movements', ['drug_id' => $drug->id, 'movement_type' => 'in', 'quantity' => 20, 'reference' => 'GRN-001']);
        $this->assertDatabaseHas('stock_movements', ['drug_id' => $drug->id, 'movement_type' => 'return', 'quantity' => 2, 'reference' => 'RET-001']);
        $this->assertDatabaseHas('stock_movements', ['drug_id' => $drug->id, 'movement_type' => 'adjustment', 'quantity' => 3, 'reference' => 'DAM-001']);
    }

    public function test_low_stock_notification_is_created_for_pharmacist(): void
    {
        Role::create(['name' => 'pharmacist', 'guard_name' => 'web']);
        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('pharmacist');
        $drug = Drug::create(['name' => 'Insulin', 'stock_quantity' => 20, 'status' => 'active']);

        $this->actingAs($pharmacist)->post('/pharmacy/dispense', ['drug_id' => $drug->id, 'quantity' => 2])->assertRedirect();

        $this->assertDatabaseHas('notifications', ['user_id' => $pharmacist->id, 'type' => 'pharmacy_stock']);
    }

    public function test_unread_low_stock_notification_is_not_duplicated_for_the_same_medicine(): void
    {
        Role::create(['name' => 'pharmacist', 'guard_name' => 'web']);
        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('pharmacist');
        $drug = Drug::create(['name' => 'Insulin repeat', 'stock_quantity' => 20, 'status' => 'active']);

        $this->actingAs($pharmacist)->post('/pharmacy/dispense', ['drug_id' => $drug->id, 'quantity' => 2])->assertRedirect();
        $this->actingAs($pharmacist)->post('/pharmacy/dispense', ['drug_id' => $drug->id, 'quantity' => 1])->assertRedirect();

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_dispensing_uses_the_earliest_non_expired_batch(): void
    {
        Role::create(['name' => 'pharmacist', 'guard_name' => 'web']);
        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('pharmacist');
        $drug = Drug::create(['name' => 'Artemether', 'stock_quantity' => 20, 'status' => 'active']);
        $expired = DrugBatch::create(['drug_id' => $drug->id, 'batch_number' => 'OLD-001', 'quantity' => 10, 'expiry_date' => now()->subDay(), 'status' => 'active']);
        $valid = DrugBatch::create(['drug_id' => $drug->id, 'batch_number' => 'NEW-001', 'quantity' => 10, 'expiry_date' => now()->addMonths(2), 'status' => 'active']);

        $this->actingAs($pharmacist)->post('/pharmacy/dispense', ['drug_id' => $drug->id, 'quantity' => 6])->assertRedirect();

        $this->assertDatabaseHas('drug_batches', ['id' => $expired->id, 'quantity' => 10]);
        $this->assertDatabaseHas('drug_batches', ['id' => $valid->id, 'quantity' => 4]);
        $this->assertDatabaseHas('stock_movements', ['drug_batch_id' => $valid->id, 'movement_type' => 'out', 'quantity' => 6]);
    }

    public function test_dispensing_uses_legacy_stock_after_available_batches_are_exhausted(): void
    {
        Role::create(['name' => 'pharmacist', 'guard_name' => 'web']);
        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('pharmacist');
        $drug = Drug::create(['name' => 'Azithromycin', 'stock_quantity' => 10, 'status' => 'active']);

        $this->actingAs($pharmacist)->post('/pharmacy/receive', [
            'drug_id' => $drug->id,
            'quantity' => 5,
            'reference' => 'GRN-MIXED-001',
            'batch_number' => 'BATCH-001',
            'expiry_date' => now()->addYear()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($pharmacist)->post('/pharmacy/dispense', [
            'drug_id' => $drug->id,
            'quantity' => 8,
            'reference' => 'RX-MIXED-001',
        ])->assertRedirect();

        $this->assertDatabaseHas('drugs', ['id' => $drug->id, 'stock_quantity' => 7]);
        $this->assertDatabaseHas('drug_batches', ['drug_id' => $drug->id, 'batch_number' => 'BATCH-001', 'quantity' => 0]);
        $this->assertDatabaseHas('stock_movements', ['drug_id' => $drug->id, 'drug_batch_id' => null, 'movement_type' => 'out', 'quantity' => 3]);
    }

    public function test_pharmacist_can_manage_catalogue_and_review_stock_history(): void
    {
        Role::create(['name' => 'pharmacist', 'guard_name' => 'web']);
        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('pharmacist');

        $this->actingAs($pharmacist)->get('/pharmacy/catalogue')->assertOk();
        $this->actingAs($pharmacist)->get('/pharmacy/history')->assertOk();
        $this->actingAs($pharmacist)->get('/pharmacy?q=Metformin')->assertOk();
        $this->actingAs($pharmacist)->post('/pharmacy/drugs', ['name' => 'Metformin', 'category' => 'Diabetes', 'form' => 'Tablet', 'unit_price' => 100, 'status' => 'active'])->assertRedirect();
        $drug = Drug::where('name', 'Metformin')->firstOrFail();
        $this->actingAs($pharmacist)->patch("/pharmacy/drugs/{$drug->id}", ['name' => 'Metformin 500mg', 'category' => 'Diabetes', 'form' => 'Tablet', 'unit_price' => 120, 'status' => 'inactive'])->assertRedirect();
        $this->actingAs($pharmacist)->get('/pharmacy/catalogue?q=Metformin')->assertOk()->assertSee('Metformin 500mg');

        $this->assertDatabaseHas('drugs', ['id' => $drug->id, 'name' => 'Metformin 500mg', 'status' => 'inactive']);
    }

    public function test_pharmacist_can_review_stock_alerts_for_low_stock_and_expiry_risk(): void
    {
        Role::create(['name' => 'pharmacist', 'guard_name' => 'web']);
        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('pharmacist');
        $lowStockDrug = Drug::create(['name' => 'Aspirin', 'stock_quantity' => 5, 'expiry_date' => now()->addMonths(2), 'status' => 'active']);
        $expiringDrug = Drug::create(['name' => 'Vitamin C', 'stock_quantity' => 40, 'expiry_date' => now()->addDays(20), 'status' => 'active']);

        $this->actingAs($pharmacist)
            ->get('/pharmacy/alerts')
            ->assertOk()
            ->assertSee('Low stock alerts')
            ->assertSee('Expiry alerts')
            ->assertSee($lowStockDrug->name)
            ->assertSee($expiringDrug->name);
    }

    public function test_low_stock_notification_is_cleared_when_stock_recovers(): void
    {
        Role::create(['name' => 'pharmacist', 'guard_name' => 'web']);
        $pharmacist = User::factory()->create();
        $pharmacist->assignRole('pharmacist');
        $drug = Drug::create(['name' => 'Insulin recovered', 'stock_quantity' => 20, 'status' => 'active']);

        $this->actingAs($pharmacist)->post('/pharmacy/dispense', ['drug_id' => $drug->id, 'quantity' => 2])->assertRedirect();
        $this->assertDatabaseCount('notifications', 1);
        $this->actingAs($pharmacist)->post('/pharmacy/adjust', ['drug_id' => $drug->id, 'movement_type' => 'return', 'quantity' => 2, 'reference' => 'RET-RECOVER', 'notes' => 'Stock recovered'])->assertRedirect();

        $this->assertDatabaseCount('notifications', 0);
    }
}