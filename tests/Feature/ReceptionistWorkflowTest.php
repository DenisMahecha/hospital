<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReceptionistWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function receptionist(): User
    {
        Role::firstOrCreate(['name' => 'receptionist', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('receptionist');
        return $user;
    }

    public function test_receptionist_cannot_open_finance_routes(): void
    {
        $receptionist = $this->receptionist();

        $this->actingAs($receptionist)->get('/billing')->assertForbidden();
        $this->actingAs($receptionist)->get('/payments')->assertForbidden();
        $this->actingAs($receptionist)->get('/dashboard')->assertSee('Open bookings')->assertDontSee('Walk-ins');
    }

    public function test_receptionist_cannot_book_inactive_patient_or_conflicting_slot(): void
    {
        $receptionist = $this->receptionist();
        $inactive = Patient::create(['patient_number' => 'PT-INACTIVE-001', 'first_name' => 'Inactive', 'last_name' => 'Patient', 'is_active' => false]);
        $active = Patient::create(['patient_number' => 'PT-ACTIVE-001', 'first_name' => 'Active', 'last_name' => 'Patient']);
        $time = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');

        $this->actingAs($receptionist)->post('/appointments', ['patient_id' => $inactive->id, 'scheduled_at' => $time, 'department' => 'OPD'])->assertStatus(422);
        $this->actingAs($receptionist)->post('/appointments', ['patient_id' => $active->id, 'scheduled_at' => $time, 'department' => 'OPD'])->assertRedirect();
        $this->actingAs($receptionist)->post('/appointments', ['patient_id' => $active->id, 'scheduled_at' => $time, 'department' => 'OPD'])->assertStatus(422);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_receptionist_can_update_and_reschedule_an_appointment(): void
    {
        $receptionist = $this->receptionist();
        $patient = Patient::create(['patient_number' => 'PT-LIFECYCLE-001', 'first_name' => 'Lifecycle', 'last_name' => 'Patient']);
        $appointment = Appointment::create(['patient_id' => $patient->id, 'appointment_number' => 'APT-LIFECYCLE-001', 'scheduled_at' => now()->addDay(), 'department' => 'OPD', 'status' => 'scheduled']);

        $this->actingAs($receptionist)->patch("/appointments/{$appointment->id}/status", ['status' => 'checked_in'])->assertRedirect();
        $this->actingAs($receptionist)->patch("/appointments/{$appointment->id}/reschedule", ['scheduled_at' => now()->addDays(2)->format('Y-m-d\TH:i')])->assertRedirect();
        $this->actingAs($receptionist)->patch("/appointments/{$appointment->id}/status", ['status' => 'cancelled'])->assertSessionHasErrors('status_reason');
        $this->actingAs($receptionist)->patch("/appointments/{$appointment->id}/status", ['status' => 'cancelled', 'status_reason' => 'Patient requested cancellation'])->assertRedirect();
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'cancelled', 'cancellation_reason' => 'Patient requested cancellation']);
    }

    public function test_patient_registry_searches_and_rejects_duplicate_phone(): void
    {
        $receptionist = $this->receptionist();
        Patient::create(['patient_number' => 'PT-SEARCH-001', 'first_name' => 'Searchable', 'last_name' => 'Person', 'phone' => '+255700111222']);

        $this->actingAs($receptionist)->get('/patients?q=Searchable')->assertOk()->assertSee('Searchable');
        $this->actingAs($receptionist)->get('/patients?q=Searchable%20Person')->assertOk()->assertSee('Searchable');
        $this->actingAs($receptionist)->get('/appointments/create?patient_search=Searchable%20Person')->assertOk()->assertSee('Searchable Person');
        $this->actingAs($receptionist)->get('/appointments/patient-suggestions?q=Searchable')->assertOk()->assertJsonFragment(['name' => 'Searchable Person']);
        $this->actingAs($receptionist)->post('/patients', ['first_name' => 'Duplicate', 'last_name' => 'Person', 'phone' => '+255 700 111 222'])->assertStatus(422);
    }

    public function test_receptionist_can_view_today_queue_and_triage_board(): void
    {
        $receptionist = $this->receptionist();
        $patientOne = Patient::create(['patient_number' => 'PT-QUEUE-001', 'first_name' => 'Queue', 'last_name' => 'One', 'is_active' => true]);
        $patientTwo = Patient::create(['patient_number' => 'PT-QUEUE-002', 'first_name' => 'Queue', 'last_name' => 'Two', 'is_active' => true]);

        Appointment::create(['patient_id' => $patientOne->id, 'appointment_number' => 'APT-QUEUE-001', 'scheduled_at' => now()->setTime(8, 0), 'department' => 'OPD', 'status' => 'scheduled']);
        Appointment::create(['patient_id' => $patientTwo->id, 'appointment_number' => 'APT-QUEUE-002', 'scheduled_at' => now()->setTime(9, 0), 'department' => 'OPD', 'status' => 'checked_in']);

        $this->actingAs($receptionist)
            ->get('/appointments/queue')
            ->assertOk()
            ->assertSee('Triage queue')
            ->assertSee('Queue One')
            ->assertSee('Queue Two');
    }
}
