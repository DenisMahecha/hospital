<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PatientTimelineWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_view_a_single_patient_timeline_across_departments(): void
    {
        Role::create(['name' => 'doctor', 'guard_name' => 'web']);
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');

        $patient = Patient::create([
            'patient_number' => 'PT-TL-001',
            'first_name' => 'Lilian',
            'last_name' => 'Maro',
            'phone' => '+255700123456',
            'is_active' => true,
        ]);

        Appointment::create([
            'patient_id' => $patient->id,
            'appointment_number' => 'APT-TL-001',
            'scheduled_at' => now()->addDay(),
            'department' => 'OPD',
            'status' => 'scheduled',
        ]);

        $visit = Visit::create([
            'patient_id' => $patient->id,
            'visit_number' => 'VIS-TL-001',
            'visit_date' => now(),
            'department' => 'OPD',
            'doctor_id' => $doctor->id,
            'complaint' => 'Fever',
            'diagnosis' => 'Malaria',
            'status' => 'completed',
        ]);

        LabRequest::create([
            'patient_id' => $patient->id,
            'request_number' => 'LAB-TL-001',
            'test_name' => 'Malaria test',
            'status' => 'completed',
            'result' => 'Positive',
            'requested_by' => $doctor->id,
        ]);

        $this->actingAs($doctor)
            ->get(route('patients.timeline', $patient))
            ->assertOk()
            ->assertSee('Clinical timeline')
            ->assertSee('Lilian Maro')
            ->assertSee('Malaria')
            ->assertSee('Malaria test');
    }
}
