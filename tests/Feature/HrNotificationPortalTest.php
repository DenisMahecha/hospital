<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\PatientPortalAccount;
use App\Models\RadiologyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HrNotificationPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_payroll_and_clinical_notifications_work_with_private_portal_data(): void
    {
        foreach (['hr', 'doctor', 'lab-technician', 'patient'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
        $hr = User::factory()->create();
        $hr->assignRole('hr');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $lab = User::factory()->create();
        $lab->assignRole('lab-technician');
        $patientUser = User::factory()->create();
        $patientUser->assignRole('patient');
        $patient = Patient::create(['patient_number' => 'PAT-PORT-001', 'first_name' => 'Portal', 'last_name' => 'User']);
        PatientPortalAccount::create(['patient_id' => $patient->id, 'user_id' => $patientUser->id, 'portal_username' => 'portal-user', 'status' => 'active']);
        $employee = Employee::create(['employee_number' => 'EMP-HR-001', 'first_name' => 'Staff', 'last_name' => 'Member', 'role' => 'Nurse', 'department' => 'Clinical', 'status' => 'active']);

        $this->actingAs($hr)->get('/hr')->assertOk()->assertSee('Hospital operations')->assertSee('People operations')->assertSee('Select department')->assertSee('Select role');
        $this->actingAs($hr)->post('/hr/payroll', ['employee_id' => $employee->id, 'month' => now()->format('Y-m'), 'basic_salary' => 1000000, 'allowances' => 100000, 'deductions' => 50000])->assertRedirect();
        $labRequest = LabRequest::create(['request_number' => 'LAB-NOT-001', 'patient_id' => $patient->id, 'requested_by' => $doctor->id, 'test_name' => 'CBC', 'status' => 'pending']);
        $this->actingAs($lab)->post("/lab/{$labRequest->id}/complete", ['result' => 'Normal'])->assertRedirect();
        $radiologyRequest = RadiologyRequest::create(['request_number' => 'RAD-NOT-001', 'patient_id' => $patient->id, 'requested_by' => $doctor->id, 'modality' => 'X-Ray', 'status' => 'completed', 'report' => 'No acute fracture detected', 'reported_by' => $doctor->id, 'reported_at' => now()]);
        $appointment = $patient->appointments()->create(['appointment_number' => 'APT-PORT-001', 'scheduled_at' => now()->addDays(2), 'department' => 'OPD', 'status' => 'scheduled', 'booked_by' => $doctor->id]);
        $patient->invoices()->create(['invoice_number' => 'INV-PORT-001', 'service_description' => 'Visit fee', 'total_amount' => 45000, 'status' => 'pending']);
        $this->actingAs($patientUser)->get('/portal')->assertOk()->assertSee('My laboratory results')->assertSee('My imaging results')->assertSee(route('portal.radiology-result', $radiologyRequest))->assertSee('Upcoming appointment')->assertSee('Outstanding balance')->assertSee($radiologyRequest->request_number)->assertSee($appointment->appointment_number)->assertSee('TZS 45,000');
        $this->actingAs($patientUser)->get('/patients')->assertForbidden();

        $this->assertDatabaseHas('payroll', ['employee_id' => $employee->id, 'net_salary' => 1050000]);
        $this->assertDatabaseHas('notifications', ['user_id' => $doctor->id, 'title' => 'Lab result ready']);
    }
}