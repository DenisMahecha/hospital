<?php

namespace Tests\Feature;

use App\Models\Drug;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\RadiologyRequest;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JourneyBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_generates_itemized_invoice_from_completed_patient_journey(): void
    {
        Role::create(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $patient = Patient::create(['patient_number' => 'PAT-JOURNEY-001', 'first_name' => 'Rehema', 'last_name' => 'Mushi']);
        $visit = Visit::create(['patient_id' => $patient->id, 'visit_number' => 'VIS-JOURNEY-001', 'visit_date' => now(), 'department' => 'OPD', 'complaint' => 'Fever', 'status' => 'completed']);
        LabRequest::create(['patient_id' => $patient->id, 'request_number' => 'LAB-JOURNEY-001', 'test_name' => 'CBC', 'status' => 'completed']);
        RadiologyRequest::create(['patient_id' => $patient->id, 'request_number' => 'RAD-JOURNEY-001', 'requested_by' => $cashier->id, 'modality' => 'X-Ray', 'status' => 'completed']);
        $drug = Drug::create(['name' => 'Amoxicillin', 'unit_price' => 12000, 'stock_quantity' => 20, 'status' => 'active']);
        Prescription::create(['patient_id' => $patient->id, 'visit_id' => $visit->id, 'prescription_number' => 'RX-JOURNEY-001', 'medication_name' => $drug->name, 'status' => 'active']);

        $this->actingAs($cashier)->get('/billing/patient-services?patient_id='.$patient->id)->assertOk()->assertJsonFragment(['description' => 'OPD consultation '.$visit->visit_number]);

        $this->actingAs($cashier)->post('/billing/journey', ['patient_id' => $patient->id])->assertRedirect('/billing');

        $invoice = $patient->invoices()->with('items')->first();
        $this->assertNotNull($invoice);
        $this->assertSame(4, $invoice->items->count());
        $this->assertSame(77000.0, (float) $invoice->total_amount);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id, 'item_type' => 'consultation']);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id, 'item_type' => 'lab']);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id, 'item_type' => 'radiology']);
        $this->assertDatabaseHas('invoice_items', ['invoice_id' => $invoice->id, 'item_type' => 'medicine']);
    }

    public function test_cashier_gets_a_billing_message_when_all_completed_services_are_already_billed(): void
    {
        Role::create(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $patient = Patient::create(['patient_number' => 'PAT-JOURNEY-002', 'first_name' => 'Mariam', 'last_name' => 'John']);
        $visit = Visit::create(['patient_id' => $patient->id, 'visit_number' => 'VIS-JOURNEY-002', 'visit_date' => now(), 'department' => 'OPD', 'complaint' => 'Cough', 'status' => 'completed']);
        $invoice = $patient->invoices()->create(['invoice_number' => 'AUTO-PAT-JOURNEY-002', 'service_description' => 'Previous journey bill', 'total_amount' => 20000, 'status' => 'pending']);
        $invoice->items()->create(['item_type' => 'consultation', 'description' => 'OPD consultation', 'quantity' => 1, 'unit_price' => 20000, 'total_price' => 20000, 'source_id' => $visit->id]);

        $this->actingAs($cashier)->post('/billing/journey', ['patient_id' => $patient->id])->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_dispensed_prescription_is_billed_using_prescribed_quantity(): void
    {
        Role::create(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $patient = Patient::create(['patient_number' => 'PAT-JOURNEY-003', 'first_name' => 'Baraka', 'last_name' => 'Mtei']);
        $drug = Drug::create(['name' => 'Metformin', 'unit_price' => 2500, 'stock_quantity' => 20, 'status' => 'active']);
        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'prescription_number' => 'RX-JOURNEY-003',
            'medication_name' => $drug->name,
            'quantity_prescribed' => 6,
            'quantity_dispensed' => 6,
            'status' => 'dispensed',
        ]);

        $this->actingAs($cashier)->post('/billing/journey', ['patient_id' => $patient->id])->assertRedirect('/billing');

        $this->assertDatabaseHas('invoice_items', [
            'item_type' => 'medicine',
            'source_id' => $prescription->id,
            'quantity' => 6,
            'unit_price' => 2500,
            'total_price' => 15000,
        ]);
    }
}