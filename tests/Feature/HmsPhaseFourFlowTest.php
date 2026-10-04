<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Patient;
use App\Models\Payroll;
use App\Models\RadiologyRequest;
use App\Models\TheatreSchedule;
use App\Models\User;
use App\Models\VaccinationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HmsPhaseFourFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_radiology_theatre_and_rch_routes_work(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super-admin');
        $this->actingAs($user);

        $this->get('/hr')->assertOk();
        $this->get('/radiology')->assertOk();
        $this->get('/theatre')->assertOk();
        $this->post('/theatre', ['surgery_name' => 'Appendectomy', 'scheduled_date' => '2026-09-25 09:00:00', 'theatre_room' => 'OT-1', 'status' => 'scheduled'])->assertRedirect('/theatre');
        $this->get('/rch')->assertOk();
        $this->post('/rch', ['child_name' => 'Aisha Salim', 'date_of_birth' => '2025-01-15', 'vaccine_name' => 'BCG', 'dose_number' => 1, 'status' => 'completed'])->assertRedirect('/rch');

        $patient = Patient::create([
            'patient_number' => 'PT-9002',
            'first_name' => 'Hassan',
            'last_name' => 'Nasser',
            'gender' => 'male',
            'date_of_birth' => '1990-12-10',
            'phone' => '+255711778899',
            'address' => 'Mbeya',
            'next_of_kin_name' => 'Mariam Nasser',
            'next_of_kin_phone' => '+255722334455',
            'insurance_provider' => 'NHIF',
        ]);

        $employee = Employee::create([
            'employee_number' => 'EMP-1001',
            'first_name' => 'Mariam',
            'last_name' => 'Kassim',
            'role' => 'doctor',
            'department' => 'Surgery',
            'status' => 'active',
        ]);

        Payroll::create([
            'employee_id' => $employee->id,
            'month' => '2026-09',
            'basic_salary' => 2500000,
            'allowances' => 350000,
            'deductions' => 120000,
            'net_salary' => 2730000,
            'status' => 'approved',
        ]);

        RadiologyRequest::create([
            'request_number' => 'RAD-1001',
            'patient_id' => $patient->id,
            'requested_by' => $employee->id,
            'modality' => 'X-Ray',
            'status' => 'pending',
        ]);

        TheatreSchedule::create([
            'surgery_name' => 'Appendectomy',
            'scheduled_date' => '2026-09-25 09:00:00',
            'theatre_room' => 'OT-1',
            'status' => 'scheduled',
        ]);

        VaccinationRecord::create([
            'child_name' => 'Aisha Salim',
            'date_of_birth' => '2025-01-15',
            'vaccine_name' => 'BCG',
            'dose_number' => 1,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('employees', ['employee_number' => 'EMP-1001']);
        $this->assertDatabaseHas('payroll', ['month' => '2026-09']);
        $this->assertDatabaseHas('radiology_requests', ['request_number' => 'RAD-1001']);
        $this->assertDatabaseHas('theatre_schedules', ['surgery_name' => 'Appendectomy']);
        $this->assertDatabaseHas('vaccination_records', ['child_name' => 'Aisha Salim']);
    }

    public function test_nursing_care_route_and_note_submission_work(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'nurse', 'guard_name' => 'web']);

        $nurse = User::factory()->create();
        $nurse->assignRole('nurse');
        $patient = Patient::create([
            'patient_number' => 'PT-9003',
            'first_name' => 'Amina',
            'last_name' => 'Juma',
            'gender' => 'female',
            'date_of_birth' => '1988-02-20',
            'phone' => '+255712345678',
            'address' => 'Dodoma',
            'next_of_kin_name' => 'Juma Amina',
            'next_of_kin_phone' => '+255765432198',
        ]);

        $ward = \App\Models\Ward::create(['name' => 'Ward A', 'capacity' => 10, 'status' => 'active']);
        $admission = \App\Models\Admission::create([
            'admission_number' => 'ADM-1001',
            'patient_id' => $patient->id,
            'ward_id' => $ward->id,
            'status' => 'admitted',
            'admission_date' => now(),
        ]);

        $this->actingAs($nurse)->get('/nursing-care')->assertOk();
        $this->actingAs($nurse)->post('/nursing-care', [
            'admission_id' => $admission->id,
            'note_type' => 'progress',
            'note' => 'Patient improving after medication.',
        ])->assertRedirect();

        $this->assertDatabaseHas('nursing_notes', [
            'admission_id' => $admission->id,
            'note_type' => 'progress',
            'note' => 'Patient improving after medication.',
        ]);
    }

    public function test_patient_journey_from_registration_to_portal_result_review(): void
    {
        foreach (['super-admin', 'receptionist', 'doctor', 'nurse', 'lab-technician', 'radiologist', 'patient'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $receptionist = User::factory()->create();
        $receptionist->assignRole('receptionist');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $labTech = User::factory()->create();
        $labTech->assignRole('lab-technician');
        $radiologist = User::factory()->create();
        $radiologist->assignRole('radiologist');
        $patientUser = User::factory()->create(['email' => 'journey.patient@example.com']);
        $patientUser->assignRole('patient');

        $patient = Patient::create([
            'patient_number' => 'PT-JOURNEY-001',
            'first_name' => 'Juma',
            'last_name' => 'Kipanga',
            'gender' => 'male',
            'date_of_birth' => '1992-04-17',
            'phone' => '+255700000003',
            'address' => 'Moshi',
            'next_of_kin_name' => 'Asha Kipanga',
            'next_of_kin_phone' => '+255700000004',
            'insurance_provider' => 'NHIF',
            'is_active' => true,
        ]);

        \App\Models\PatientPortalAccount::create([
            'patient_id' => $patient->id,
            'user_id' => $patientUser->id,
            'portal_username' => 'juma.portal',
            'status' => 'active',
        ]);

        $this->actingAs($receptionist)->post('/appointments', [
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'department' => 'OPD',
            'reason' => 'Follow-up consultation',
        ])->assertRedirect('/appointments');

        $appointment = \App\Models\Appointment::where('patient_id', $patient->id)->firstOrFail();
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'scheduled']);
        $this->actingAs($receptionist)->patch('/appointments/'.$appointment->id.'/status', ['status' => 'checked_in'])->assertRedirect();

        $this->actingAs($doctor)->post('/visits', [
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'department' => 'OPD',
            'complaint' => 'Fever and cough',
            'diagnosis' => 'Upper respiratory infection',
            'icd10_code' => 'J06.9',
            'notes' => 'Reviewed and advised rest',
        ])->assertRedirect('/visits');

        $visit = $patient->visits()->firstOrFail();
        $this->actingAs($doctor)->post('/lab', ['patient_id' => $patient->id, 'test_name' => 'CBC', 'notes' => 'Routine profile'])->assertRedirect();
        $labRequest = $patient->labRequests()->firstOrFail();

        $this->actingAs($labTech)->post('/lab/'.$labRequest->id.'/status', ['status' => 'collected'])->assertRedirect();
        $this->actingAs($labTech)->post('/lab/'.$labRequest->id.'/complete', ['result' => 'Normal', 'reference_range' => '4-10'])->assertRedirect();

        $this->actingAs($doctor)->post('/radiology', ['patient_id' => $patient->id, 'modality' => 'X-Ray'])->assertRedirect();
        $radiologyRequest = $patient->radiologyRequests()->firstOrFail();
        $this->actingAs($radiologist)->post('/radiology/'.$radiologyRequest->id.'/report', ['report' => 'No fracture detected'])->assertRedirect();

        $this->actingAs($patientUser)->get('/portal')->assertOk()->assertSee('My laboratory results')->assertSee('My imaging results');
        $this->actingAs($patientUser)->get(route('portal.lab-result', $labRequest))->assertOk()->assertSee('Normal');
        $this->actingAs($patientUser)->get(route('portal.radiology-result', $radiologyRequest))->assertOk()->assertSee('No fracture detected');

        $this->assertDatabaseHas('lab_result_reads', ['lab_request_id' => $labRequest->id, 'user_id' => $patientUser->id]);
        $this->assertDatabaseHas('radiology_result_reads', ['radiology_request_id' => $radiologyRequest->id, 'user_id' => $patientUser->id]);
    }
}
