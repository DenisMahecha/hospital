<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClinicalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinical_roles_complete_consultation_diagnostics_and_vitals(): void
    {
        foreach (['doctor', 'nurse', 'lab-technician', 'radiologist'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $nurse = User::factory()->create();
        $nurse->assignRole('nurse');
        $lab = User::factory()->create();
        $lab->assignRole('lab-technician');
        $radiologist = User::factory()->create();
        $radiologist->assignRole('radiologist');
        $patient = Patient::create(['patient_number' => 'PAT-CLIN-001', 'first_name' => 'Asha', 'last_name' => 'Mushi']);
        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_number' => 'APT-CLIN-001',
            'scheduled_at' => now()->addHour(),
            'department' => 'OPD',
            'status' => 'checked_in',
        ]);

        $this->actingAs($doctor)->post('/visits', [
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'department' => 'OPD',
            'complaint' => 'Fever',
            'diagnosis' => 'Upper respiratory infection',
            'icd10_code' => 'J06.9',
            'notes' => 'Clinical review completed.',
        ])->assertRedirect('/visits');

        $unassignedPatient = Patient::create(['patient_number' => 'PAT-CLIN-002', 'first_name' => 'Musa', 'last_name' => 'Juma']);
        $this->actingAs($doctor)->post('/visits', [
            'patient_id' => $unassignedPatient->id,
            'appointment_id' => $appointment->id,
            'department' => 'OPD',
            'complaint' => 'Unauthorized consultation',
        ])->assertStatus(422);

        $visit = $patient->visits()->first();
        $this->actingAs($nurse)->post("/visits/{$visit->id}/vitals", ['temperature' => 38.2, 'blood_pressure' => '120/80', 'heart_rate' => 90])->assertForbidden();
        $this->actingAs($doctor)->post('/lab', ['patient_id' => $patient->id, 'request_number' => 'LAB-CLIN-001', 'test_name' => 'CBC'])->assertRedirect();
        $labRequest = $patient->labRequests()->first();
        $this->actingAs($lab)->post("/lab/{$labRequest->id}/complete", ['result' => 'Normal', 'reference_range' => '4-10'])->assertRedirect();
        $this->actingAs($doctor)->post('/radiology', ['patient_id' => $patient->id, 'request_number' => 'RAD-CLIN-001', 'modality' => 'X-Ray'])->assertRedirect();
        $radiologyRequest = $patient->radiologyRequests()->first();
        $this->actingAs($radiologist)->post("/radiology/{$radiologyRequest->id}/report", ['report' => 'No acute findings'])->assertRedirect();

        $this->assertDatabaseHas('visits', ['visit_number' => 'VIS-'.now()->format('Ymd').'-0001', 'diagnosis' => 'Upper respiratory infection']);
        $this->assertDatabaseMissing('vitals', ['visit_id' => $visit->id, 'temperature' => 38.2]);
        $this->assertDatabaseHas('lab_requests', ['request_number' => 'LAB-'.now()->format('Ymd').'-0001', 'status' => 'completed', 'result' => 'Normal']);
        $this->assertDatabaseHas('radiology_requests', ['request_number' => 'RAD-'.now()->format('Ymd').'-0001', 'status' => 'completed', 'report' => 'No acute findings']);
    }

    public function test_radiologist_uploads_imaging_report_and_notifies_requester(): void
    {
        Storage::fake('local');
        foreach (['doctor', 'radiologist'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $radiologist = User::factory()->create(['email' => 'radiologist@gmail.com']);
        $radiologist->assignRole('radiologist');
        $patient = Patient::create(['patient_number' => 'PAT-RAD-001', 'first_name' => 'Rehema', 'last_name' => 'Said']);
        $appointment = Appointment::create(['patient_id' => $patient->id, 'doctor_id' => $doctor->id, 'appointment_number' => 'APT-RAD-001', 'scheduled_at' => now()->addHour(), 'department' => 'OPD', 'status' => 'checked_in']);

        $this->actingAs($doctor)->post('/radiology', ['patient_id' => $patient->id, 'modality' => 'CT'])->assertRedirect();
        $request = $patient->radiologyRequests()->firstOrFail();
        $this->actingAs($radiologist)->post("/radiology/{$request->id}/report", ['report' => 'CT shows no acute abnormality.', 'image' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf')])->assertRedirect();

        $request->refresh();
        Storage::disk('local')->assertExists($request->image_path);
        $this->assertDatabaseHas('radiology_requests', ['id' => $request->id, 'status' => 'completed', 'reported_by' => $radiologist->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $doctor->id, 'type' => 'radiology_result']);
        $this->actingAs($doctor)->get(route('radiology.attachment', $request))->assertOk();
        $this->actingAs($radiologist)->post("/radiology/{$request->id}/report", ['report' => 'Changed report'])->assertStatus(422);
    }
}