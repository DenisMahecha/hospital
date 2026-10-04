<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use App\Models\Ward;
use App\Models\NursingNote;
use App\Models\AdmissionVital;
use App\Models\MedicationAdministration;
use App\Models\AdmissionTransfer;
use App\Models\WardRound;
use App\Models\Visit;
use App\Models\Appointment;
use App\Models\Bed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HmsPhaseThreeFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_ipd_emergency_and_billing_routes_work(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user);

        $this->get('/wards')->assertOk();
        $this->get('/admissions')->assertOk();
        $this->get('/billing')->assertOk();
        $this->get('/emergency')->assertOk();

        $patient = Patient::create([
            'patient_number' => 'PT-9001',
            'first_name' => 'Moses',
            'last_name' => 'Sanga',
            'gender' => 'male',
            'date_of_birth' => '1988-02-03',
            'phone' => '+255712000111',
            'address' => 'Dodoma',
            'next_of_kin_name' => 'Tatu Sanga',
            'next_of_kin_phone' => '+255711000222',
            'insurance_provider' => 'NHIF',
        ]);

        $ward = Ward::create([
            'name' => 'Male Ward',
            'capacity' => 20,
            'status' => 'available',
        ]);

        $admission = Admission::create([
            'patient_id' => $patient->id,
            'ward_id' => $ward->id,
            'admission_number' => 'ADM-1001',
            'admission_date' => now(),
            'status' => 'admitted',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-1001',
            'patient_id' => $patient->id,
            'admission_id' => $admission->id,
            'total_amount' => 350000,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('wards', ['name' => 'Male Ward']);
        $this->assertDatabaseHas('admissions', ['admission_number' => 'ADM-1001']);
        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV-1001']);
    }

    public function test_nurse_records_notes_and_capacity_is_enforced(): void
    {
        Role::firstOrCreate(['name' => 'nurse', 'guard_name' => 'web']);
        $nurse = User::factory()->create();
        $nurse->assignRole('nurse');
        $ward = Ward::create(['name' => 'Nurse Test Ward', 'capacity' => 1, 'status' => 'available']);
        $patient = Patient::create(['patient_number' => 'PAT-NURSE-001', 'first_name' => 'Mariam', 'last_name' => 'Kassim']);
        $secondPatient = Patient::create(['patient_number' => 'PAT-NURSE-002', 'first_name' => 'Juma', 'last_name' => 'Said']);

        $this->actingAs($nurse)->post('/admissions', ['patient_id' => $patient->id, 'ward_id' => $ward->id])->assertRedirect();
        $admission = $patient->admissions()->firstOrFail();
        $this->actingAs($nurse)->post("/admissions/{$admission->id}/nursing-notes", ['note_type' => 'observation', 'note' => 'Patient stable and responsive.'])->assertRedirect();
        $this->actingAs($nurse)->post('/admissions', ['patient_id' => $secondPatient->id, 'ward_id' => $ward->id])->assertStatus(422);
        $this->actingAs($nurse)->post("/admissions/{$admission->id}/discharge", ['discharge_summary' => 'Stable for home care.', 'discharge_condition' => 'Stable'])->assertRedirect();
        $this->actingAs($nurse)->post("/admissions/{$admission->id}/discharge", ['discharge_summary' => 'Duplicate discharge.', 'discharge_condition' => 'Stable'])->assertStatus(422);

        $this->assertDatabaseHas('nursing_notes', ['admission_id' => $admission->id, 'note_type' => 'observation']);
        $this->assertDatabaseHas('admissions', ['id' => $admission->id, 'status' => 'discharged']);
    }

    public function test_nurse_completes_ipd_clinical_care_and_discharge_summary(): void
    {
        Role::firstOrCreate(['name' => 'nurse', 'guard_name' => 'web']);
        $nurse = User::factory()->create();
        $nurse->assignRole('nurse');
        $fromWard = Ward::create(['name' => 'Medical IPD', 'capacity' => 2, 'status' => 'available']);
        $toWard = Ward::create(['name' => 'Surgical IPD', 'capacity' => 2, 'status' => 'available']);
        $patient = Patient::create(['patient_number' => 'PAT-IPD-CARE-001', 'first_name' => 'Amina', 'last_name' => 'John']);

        $this->actingAs($nurse)->post('/admissions', ['patient_id' => $patient->id, 'ward_id' => $fromWard->id])->assertRedirect();
        $admission = $patient->admissions()->firstOrFail();
        $this->actingAs($nurse)->post('/admissions', ['patient_id' => $patient->id, 'ward_id' => $fromWard->id])->assertStatus(422);
        $this->actingAs($nurse)->post("/admissions/{$admission->id}/vitals", ['temperature' => 37.2, 'blood_pressure' => '118/76', 'pulse' => 78, 'respiratory_rate' => 18, 'oxygen_saturation' => 98, 'consciousness' => 'alert'])->assertRedirect();
        $this->actingAs($nurse)->post("/admissions/{$admission->id}/medications", ['medication_name' => 'Paracetamol', 'dose' => '500mg', 'route' => 'oral', 'status' => 'given', 'notes' => 'Tolerated'])->assertRedirect();
        $this->actingAs($nurse)->post("/admissions/{$admission->id}/ward-rounds", ['note' => 'Patient improving.', 'plan' => 'Continue monitoring.'])->assertRedirect();
        $this->actingAs($nurse)->post("/admissions/{$admission->id}/transfer", ['to_ward_id' => $toWard->id, 'reason' => 'Requires surgical observation.'])->assertRedirect();
        $this->actingAs($nurse)->post("/admissions/{$admission->id}/discharge", ['discharge_summary' => 'Recovered after inpatient care.', 'discharge_condition' => 'Stable', 'discharge_medications' => 'Paracetamol as needed', 'follow_up_date' => today()->addWeek()->toDateString()])->assertRedirect();

        $this->assertDatabaseHas('admission_vitals', ['admission_id' => $admission->id, 'oxygen_saturation' => 98]);
        $this->assertDatabaseHas('medication_administrations', ['admission_id' => $admission->id, 'status' => 'given']);
        $this->assertDatabaseHas('ward_rounds', ['admission_id' => $admission->id]);
        $this->assertDatabaseHas('admission_transfers', ['admission_id' => $admission->id, 'to_ward_id' => $toWard->id]);
        $this->assertDatabaseHas('admissions', ['id' => $admission->id, 'status' => 'discharged', 'discharge_condition' => 'Stable']);
    }

    public function test_doctor_zone_page_and_vitals_permission_are_ready(): void
    {
        Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $patient = Patient::create(['patient_number' => 'PAT-DOCTOR-001', 'first_name' => 'Grace', 'last_name' => 'Kivuva']);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_number' => 'APT-DOCTOR-001',
            'scheduled_at' => now()->addHour(),
            'department' => 'OPD',
            'status' => 'checked_in',
        ]);
        $visit = Visit::create([
            'patient_id' => $patient->id,
            'visit_number' => 'VIS-9001',
            'visit_date' => now(),
            'department' => 'OPD',
            'doctor_id' => $doctor->id,
            'complaint' => 'Fever',
            'status' => 'completed',
        ]);

        $this->actingAs($doctor)
            ->get('/doctor-zone')
            ->assertOk()
            ->assertSee('Doctor zone')
            ->assertSee('Consultations')
            ->assertSee('Vitals')
            ->assertSee('APT-DOCTOR-001');

        $this->actingAs($doctor)
            ->post("/visits/{$visit->id}/vitals", ['temperature' => 37.2, 'blood_pressure' => '118/76', 'weight' => 58.5, 'heart_rate' => 78, 'respiratory_rate' => 18])
            ->assertRedirect();

        $this->assertDatabaseHas('vitals', ['visit_id' => $visit->id, 'temperature' => 37.2]);
    }

    public function test_manager_creates_ward_with_beds_and_admission_auto_assigns_one(): void
    {
        Role::firstOrCreate(['name' => 'hospital-manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'nurse', 'guard_name' => 'web']);
        $manager = User::factory()->create();
        $manager->assignRole('hospital-manager');
        $nurse = User::factory()->create();
        $nurse->assignRole('nurse');
        $patient = Patient::create(['patient_number' => 'PAT-BED-001', 'first_name' => 'Neema', 'last_name' => 'Paul']);

        $this->actingAs($manager)->post('/wards', ['name' => 'Auto Bed Ward', 'capacity' => 2, 'status' => 'active'])->assertRedirect();
        $ward = Ward::where('name', 'Auto Bed Ward')->firstOrFail();
        $this->assertSame(2, Bed::where('ward_id', $ward->id)->count());
        $this->actingAs($nurse)->post('/admissions', ['patient_id' => $patient->id, 'ward_id' => $ward->id])->assertRedirect();

        $admission = $patient->admissions()->firstOrFail();
        $this->assertNotNull($admission->bed_id);
        $this->assertDatabaseHas('beds', ['id' => $admission->bed_id, 'ward_id' => $ward->id]);
    }
}
