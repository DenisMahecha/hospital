<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HmsPhaseOneFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_role_and_patient_registration_are_supported(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin@hospital.test',
        ]);

        $admin->assignRole('super-admin');

        $this->assertTrue($admin->hasRole('super-admin'));

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
}
