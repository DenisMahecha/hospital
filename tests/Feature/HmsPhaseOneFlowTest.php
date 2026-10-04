<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HmsPhaseOneFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_phase_one_hospital_dashboard_and_create_patient(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin@hospital.test',
        ]);
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/patients')
            ->assertOk();

        $patient = Patient::create([
            'patient_number' => 'PT-1001',
            'first_name' => 'Amina',
            'last_name' => 'Mushi',
            'gender' => 'female',
            'date_of_birth' => '1995-03-12',
            'phone' => '+255712345678',
            'address' => 'Dar es Salaam',
            'next_of_kin_name' => 'Juma Mushi',
            'next_of_kin_phone' => '+255711111111',
            'insurance_provider' => 'NHIF',
        ]);

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'patient_number' => 'PT-1001',
        ]);
    }

    public function test_dashboard_adapts_to_a_doctor_role(): void
    {
        Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);

        $doctor = User::factory()->create([
            'name' => 'Doctor Amina',
            'email' => 'doctor@hospital.test',
        ]);
        $doctor->assignRole('doctor');

        $this->actingAs($doctor)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Doctor Dashboard')
            ->assertSee('Clinical workspace');
    }

    public function test_patient_records_cannot_be_deleted(): void
    {
        $patient = Patient::create(['patient_number' => 'PT-DELETE-1', 'first_name' => 'Protected', 'last_name' => 'Record']);

        $this->expectException(LogicException::class);
        $patient->delete();
    }

    public function test_patient_purge_command_removes_all_patient_records(): void
    {
        Patient::create(['patient_number' => 'PT-PURGE-1', 'first_name' => 'Delete', 'last_name' => 'Me']);
        Patient::create(['patient_number' => 'PT-PURGE-2', 'first_name' => 'Delete', 'last_name' => 'Them']);

        $this->artisan('patients:purge', ['--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Deleted 2 patient records');

        $this->assertSame(0, Patient::count());
    }
}
