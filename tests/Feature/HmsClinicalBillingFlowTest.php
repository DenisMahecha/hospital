<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Drug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HmsClinicalBillingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_appointments_prescriptions_and_payments_work(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        $patient = Patient::create([
            'patient_number' => 'PT-CLIN-1',
            'first_name' => 'Neema',
            'last_name' => 'Joseph',
            'gender' => 'female',
        ]);

        $this->actingAs($staff)
            ->get('/appointments')->assertOk();
        $this->actingAs($staff)
            ->get('/prescriptions')->assertOk()->assertSee('quantity_prescribed');
        Drug::create(['name' => 'Amoxicillin 500mg', 'category' => 'Antibiotic', 'form' => 'Capsule', 'status' => 'active']);
        $this->actingAs($staff)->get('/prescriptions/drug-suggestions?q=Amoxicillin')->assertOk()->assertJsonFragment(['name' => 'Amoxicillin 500mg']);
        $this->actingAs($staff)
            ->get('/payments')->assertOk();

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'booked_by' => $staff->id,
            'appointment_number' => 'APT-1001',
            'scheduled_at' => '2026-09-25 10:00:00',
            'department' => 'OPD',
            'reason' => 'Routine review',
            'status' => 'scheduled',
        ]);

        $prescription = Prescription::create([
            'patient_id' => $patient->id,
            'prescribed_by' => $staff->id,
            'prescription_number' => 'RX-1001',
            'medication_name' => 'Paracetamol 500mg',
            'dosage' => '1 tablet',
            'frequency' => 'Three times daily',
            'duration' => '5 days',
            'status' => 'active',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-CLIN-1',
            'patient_id' => $patient->id,
            'total_amount' => 120000,
            'status' => 'pending',
        ]);

        $this->actingAs($staff)
            ->post('/payments', [
                'invoice_id' => $invoice->id,
                'amount' => 120000,
                'payment_method' => 'cash',
                'reference' => 'CASH-1001',
            ])
            ->assertRedirect('/payments');

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'scheduled']);
        $this->assertDatabaseHas('prescriptions', ['id' => $prescription->id, 'prescription_number' => 'RX-1001']);
        $this->assertDatabaseHas('payments', ['invoice_id' => $invoice->id, 'amount' => 120000]);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid']);

        $duplicateInvoice = Invoice::create(['invoice_number' => 'INV-CLIN-2', 'patient_id' => $patient->id, 'total_amount' => 50000, 'status' => 'pending']);
        $this->actingAs($staff)->post('/payments', ['invoice_id' => $duplicateInvoice->id, 'amount' => 50000, 'payment_method' => 'mobile_money', 'reference' => 'TX-100'])->assertRedirect('/payments');
        $this->actingAs($staff)->post('/payments', ['invoice_id' => $duplicateInvoice->id, 'amount' => 1, 'payment_method' => 'mobile_money', 'reference' => 'TX-100'])->assertStatus(422);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_appointment_creation_generates_number_and_uses_department_dropdown(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $staff = User::factory()->create();
        $staff->assignRole('super-admin');
        $patient = Patient::create([
            'patient_number' => 'PT-CLIN-APT',
            'first_name' => 'Asha',
            'last_name' => 'Kibwe',
            'gender' => 'female',
        ]);

        $this->actingAs($staff)->get('/appointments')->assertOk()->assertSee('Book appointment');
        $this->actingAs($staff)->get('/appointments/create')->assertOk()->assertSee('Select department');
        $this->actingAs($staff)->get('/appointments/create')->assertSee('<option value="OPD">OPD</option>', false)->assertSee('<option value="Emergency">Emergency</option>', false)->assertSee('<option value="Cardiology">Cardiology</option>', false)->assertDontSee('<option value="Pharmacy">Pharmacy</option>', false)->assertDontSee('<option value="Laboratory">Laboratory</option>', false)->assertDontSee('<option value="Theatre">Theatre</option>', false);

        $this->actingAs($staff)->post('/appointments', [
            'patient_id' => $patient->id,
            'scheduled_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'department' => 'OPD',
            'reason' => 'Follow-up consultation',
        ])->assertRedirect('/appointments');

        $appointment = Appointment::query()->latest()->first();
        $this->assertNotNull($appointment);
        $this->assertMatchesRegularExpression('/^APT-\d{8}-[A-Z0-9]{4}$/', $appointment->appointment_number);
        $this->assertSame('OPD', $appointment->department);
    }
}
