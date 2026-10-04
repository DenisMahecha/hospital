<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MedicalZonesAndMortuaryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_icu_labour_and_death_record_workflows_are_available(): void
    {
        foreach (['super-admin', 'doctor', 'nurse'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $patient = Patient::create([
            'patient_number' => 'PT-ICU-001',
            'first_name' => 'Rose',
            'last_name' => 'Moshi',
            'gender' => 'female',
            'date_of_birth' => '1995-04-01',
            'phone' => '+255700000006',
            'address' => 'Arusha',
            'next_of_kin_name' => 'Moshi Family',
            'next_of_kin_phone' => '+255700000007',
            'insurance_provider' => 'NHIF',
        ]);

        $this->actingAs($admin)
            ->post('/wards', [
                'name' => 'ICU High Care',
                'zone' => 'ICU',
                'ward_type' => 'critical-care',
                'gender_group' => 'mixed',
                'capacity' => 4,
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('wards', ['name' => 'ICU High Care', 'zone' => 'ICU', 'gender_group' => 'mixed']);

        $this->actingAs($admin)
            ->post('/wards', [
                'name' => 'Labour Ward',
                'zone' => 'Labour & Maternity',
                'ward_type' => 'maternity',
                'gender_group' => 'women',
                'capacity' => 6,
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('wards', ['name' => 'Labour Ward', 'zone' => 'Labour & Maternity', 'gender_group' => 'women']);

        $this->actingAs($admin)->get('/deaths')->assertOk();
        $this->actingAs($admin)->post('/deaths', [
            'patient_id' => $patient->id,
            'date_of_death' => now()->toDateString(),
            'time_of_death' => now()->format('H:i'),
            'cause' => 'Cardiac arrest',
            'location' => 'ICU',
            'doctor_name' => 'Dr. Msuya',
            'notes' => 'Patient passed away after deterioration.',
        ])->assertRedirect();

        $this->assertDatabaseHas('death_records', [
            'patient_id' => $patient->id,
            'cause' => 'Cardiac arrest',
            'location' => 'ICU',
        ]);
    }
}
