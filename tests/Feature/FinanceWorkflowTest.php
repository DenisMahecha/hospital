<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_create_an_invoice_for_a_patient(): void
    {
        Role::create(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $patient = Patient::create(['patient_number' => 'PAT-BILL-001', 'first_name' => 'Amina', 'last_name' => 'Mushi']);

        $this->actingAs($cashier)->get('/billing')->assertOk()->assertSee('Create invoice');
        $this->actingAs($cashier)->get('/billing/patient-suggestions?q=Amina%20Mushi')->assertOk()->assertJsonFragment(['name' => 'Amina Mushi']);
        $this->actingAs($cashier)->post('/billing', ['patient_id' => $patient->id, 'invoice_number' => 'INV-BILL-001', 'service_description' => 'OPD consultation', 'total_amount' => 25000])->assertRedirect();
        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-'.now()->format('Ymd').'-0001', 'patient_id' => $patient->id, 'service_description' => 'OPD consultation', 'total_amount' => 25000, 'status' => 'pending']);
    }

    public function test_cashier_submits_claim_and_reconciles_daily_payments(): void
    {
        foreach (['cashier', 'hospital-manager'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $manager = User::factory()->create();
        $manager->assignRole('hospital-manager');
        $patient = Patient::create(['patient_number' => 'PAT-FIN-001', 'first_name' => 'Neema', 'last_name' => 'John']);
        $invoice = Invoice::create(['invoice_number' => 'INV-FIN-001', 'patient_id' => $patient->id, 'total_amount' => 100000, 'status' => 'partially_paid']);
        Payment::create(['invoice_id' => $invoice->id, 'received_by' => $cashier->id, 'amount' => 25000, 'payment_method' => 'cash', 'paid_at' => today(), 'status' => 'completed']);
        $this->actingAs($cashier)->get('/payments/system-total?business_date='.today()->format('Y-m-d'))->assertOk()->assertJson(['total' => 25000]);

        $this->actingAs($cashier)->post('/insurance', ['invoice_id' => $invoice->id, 'patient_id' => $patient->id, 'provider' => 'NHIF', 'claim_number' => 'CLM-FIN-001', 'amount' => 75000])->assertRedirect();
        $this->actingAs($manager)->post('/insurance/1/review', ['status' => 'approved'])->assertRedirect();
        $this->actingAs($cashier)->post('/reconciliation', ['business_date' => today()->format('Y-m-d'), 'declared_total' => 25000])->assertRedirect();

        $this->assertDatabaseHas('insurance_claims', ['claim_number' => 'CLM-'.now()->format('Ymd').'-0001', 'status' => 'approved']);
        $this->assertDatabaseHas('cashier_reconciliations', ['cashier_id' => $cashier->id, 'system_total' => 25000, 'variance' => 0]);
    }

    public function test_cashier_can_review_outstanding_collections_queue(): void
    {
        Role::create(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $patient = Patient::create(['patient_number' => 'PAT-COLL-001', 'first_name' => 'Khadija', 'last_name' => 'Salim']);
        $invoice = Invoice::create(['invoice_number' => 'INV-COLL-001', 'patient_id' => $patient->id, 'total_amount' => 75000, 'status' => 'partially_paid']);
        Payment::create(['invoice_id' => $invoice->id, 'received_by' => $cashier->id, 'amount' => 20000, 'payment_method' => 'cash', 'paid_at' => today(), 'status' => 'completed']);

        $this->actingAs($cashier)
            ->get('/billing/collections')
            ->assertOk()
            ->assertSee('Outstanding collections')
            ->assertSee('Khadija Salim')
            ->assertSee('TZS 55,000');
    }

    public function test_cashier_is_redirected_to_a_printable_receipt_after_payment(): void
    {
        Role::create(['name' => 'cashier', 'guard_name' => 'web']);
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $patient = Patient::create(['patient_number' => 'PAT-RECEIPT-001', 'first_name' => 'Receipt', 'last_name' => 'Patient']);
        $invoice = Invoice::create(['invoice_number' => 'INV-RECEIPT-001', 'patient_id' => $patient->id, 'total_amount' => 45000, 'status' => 'pending']);

        $response = $this->actingAs($cashier)->post('/payments', ['invoice_id' => $invoice->id, 'amount' => 45000, 'payment_method' => 'cash', 'reference' => 'CASH-001']);
        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $response->assertRedirect('/payments');
        $this->actingAs($cashier)->get('/payments/'.$payment->id.'/receipt')->assertOk()->assertSee('Official payment receipt')->assertSee('TZS 45,000.00')->assertSee('Print receipt');
    }
}