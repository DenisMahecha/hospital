<?php

namespace Tests\Feature;

use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\RadiologyRequest;
use App\Models\User;
use App\Models\PatientPortalAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LaboratoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_request_cannot_be_completed_twice_and_can_be_amended_with_history(): void
    {
        Role::create(['name' => 'doctor', 'guard_name' => 'web']);
        Role::create(['name' => 'lab-technician', 'guard_name' => 'web']);
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $lab = User::factory()->create();
        $lab->assignRole('lab-technician');
        $patient = Patient::create(['patient_number' => 'PAT-LAB-001', 'first_name' => 'Neema', 'last_name' => 'Juma']);

        $this->actingAs($doctor)->post('/lab', ['patient_id' => $patient->id, 'test_name' => 'CBC'])->assertRedirect();
        $request = $patient->labRequests()->firstOrFail();

        $this->actingAs($lab)->post("/lab/{$request->id}/complete", ['result' => 'Normal'])->assertRedirect();
        $this->actingAs($lab)->get('/lab')->assertOk()->assertSee('Amend result')->assertSee('View history');
        $this->actingAs($lab)->get(route('lab.amend.form', $request))->assertOk()->assertSee('Corrected result')->assertSee('Reason for amendment');
        $this->actingAs($lab)->post("/lab/{$request->id}/complete", ['result' => 'Changed'])->assertStatus(422);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('lab_result_histories', ['lab_request_id' => $request->id, 'action' => 'completed', 'result' => 'Normal']);

        $this->actingAs($lab)->post("/lab/{$request->id}/amend", ['result' => 'Abnormal', 'reason' => 'Verified against analyser printout'])->assertRedirect();
        $this->assertDatabaseHas('lab_requests', ['id' => $request->id, 'result' => 'Abnormal']);
        $this->assertDatabaseHas('lab_result_histories', ['lab_request_id' => $request->id, 'action' => 'amended', 'result' => 'Abnormal']);
    }

    public function test_doctor_only_sees_laboratory_requests_they_created(): void
    {
        Role::create(['name' => 'doctor', 'guard_name' => 'web']);
        $firstDoctor = User::factory()->create();
        $firstDoctor->assignRole('doctor');
        $secondDoctor = User::factory()->create();
        $secondDoctor->assignRole('doctor');
        $patient = Patient::create(['patient_number' => 'PAT-LAB-002', 'first_name' => 'Hassan', 'last_name' => 'Musa']);

        LabRequest::create(['request_number' => 'LAB-ONE', 'patient_id' => $patient->id, 'requested_by' => $firstDoctor->id, 'test_name' => 'CBC', 'status' => 'pending']);
        LabRequest::create(['request_number' => 'LAB-TWO', 'patient_id' => $patient->id, 'requested_by' => $secondDoctor->id, 'test_name' => 'Malaria', 'status' => 'pending']);

        $this->actingAs($firstDoctor)->get('/lab')->assertOk()->assertSee('LAB-ONE')->assertDontSee('LAB-TWO');
    }

    public function test_sample_can_move_through_collection_processing_and_completion(): void
    {
        Role::create(['name' => 'doctor', 'guard_name' => 'web']);
        Role::create(['name' => 'lab-technician', 'guard_name' => 'web']);
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $lab = User::factory()->create();
        $lab->assignRole('lab-technician');
        $patient = Patient::create(['patient_number' => 'PAT-LAB-003', 'first_name' => 'Zawadi', 'last_name' => 'Said']);
        $request = LabRequest::create(['request_number' => 'LAB-STATUS-001', 'patient_id' => $patient->id, 'requested_by' => $doctor->id, 'test_name' => 'CBC', 'status' => 'pending']);

        $this->actingAs($lab)->post("/lab/{$request->id}/status", ['status' => 'collected'])->assertRedirect();
        $this->actingAs($lab)->post("/lab/{$request->id}/status", ['status' => 'processing'])->assertRedirect();
        $this->actingAs($lab)->post("/lab/{$request->id}/complete", ['result' => 'Normal'])->assertRedirect();

        $this->assertDatabaseHas('lab_requests', ['id' => $request->id, 'status' => 'completed']);
        $this->assertNotNull(LabRequest::find($request->id)->processing_started_at);
    }

    public function test_patient_portal_records_who_read_a_completed_lab_result(): void
    {
        Role::create(['name' => 'patient', 'guard_name' => 'web']);
        $patientUser = User::factory()->create();
        $patientUser->assignRole('patient');
        $patient = Patient::create(['patient_number' => 'PAT-LAB-004', 'first_name' => 'Mariam', 'last_name' => 'Ali']);
        PatientPortalAccount::create(['patient_id' => $patient->id, 'user_id' => $patientUser->id, 'portal_username' => 'mariam.lab', 'status' => 'active']);
        $request = LabRequest::create(['request_number' => 'LAB-READ-001', 'patient_id' => $patient->id, 'test_name' => 'CBC', 'status' => 'completed', 'result' => 'Normal', 'completed_at' => now()]);

        $this->actingAs($patientUser)->get(route('portal.lab-result', $request))->assertOk()->assertSee('Normal');
        $this->assertDatabaseHas('lab_result_reads', ['lab_request_id' => $request->id, 'user_id' => $patientUser->id]);
    }

    public function test_patient_portal_records_who_read_a_completed_radiology_result(): void
    {
        Role::create(['name' => 'patient', 'guard_name' => 'web']);
        $patientUser = User::factory()->create();
        $patientUser->assignRole('patient');
        $patient = Patient::create(['patient_number' => 'PAT-RAD-004', 'first_name' => 'Asha', 'last_name' => 'Khamisi']);
        PatientPortalAccount::create(['patient_id' => $patient->id, 'user_id' => $patientUser->id, 'portal_username' => 'asha.radiology', 'status' => 'active']);
        $request = RadiologyRequest::create(['request_number' => 'RAD-READ-001', 'patient_id' => $patient->id, 'modality' => 'X-Ray', 'status' => 'completed', 'report' => 'No fracture detected', 'reported_by' => $patientUser->id, 'reported_at' => now()]);

        $this->actingAs($patientUser)->get(route('portal.radiology-result', $request))->assertOk()->assertSee('No fracture detected');
        $this->assertDatabaseHas('radiology_result_reads', ['radiology_request_id' => $request->id, 'user_id' => $patientUser->id]);
    }
}
