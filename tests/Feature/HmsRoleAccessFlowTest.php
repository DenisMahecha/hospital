<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HmsRoleAccessFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_receive_unique_dashboards_and_module_boundaries(): void
    {
        foreach (['super-admin', 'doctor', 'cashier', 'patient', 'store-keeper', 'hospital-manager', 'receptionist', 'hr', 'radiologist'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $doctor = User::factory()->create(['name' => 'Doctor User']);
        $doctor->assignRole('doctor');
        $this->actingAs($doctor)->get('/dashboard')->assertSee('Doctor Dashboard')->assertSee('Clinical workspace');
        $this->actingAs($doctor)->get('/billing')->assertForbidden();

        $cashier = User::factory()->create(['name' => 'Cashier User']);
        $cashier->assignRole('cashier');
        $this->actingAs($cashier)->get('/dashboard')->assertSee('Billing Dashboard')->assertSee('Revenue workspace')->assertSee('Create / view invoices')->assertSee('Record payments')->assertSee('Insurance claims')->assertDontSee('Register patient');
        $this->actingAs($cashier)->get('/lab')->assertForbidden();

        $patient = User::factory()->create(['name' => 'Patient User']);
        $patient->assignRole('patient');
        $this->actingAs($patient)->get('/dashboard')->assertSee('Patient Dashboard')->assertSee('Personal health workspace');
        $this->actingAs($patient)->get('/patients')->assertForbidden();

        $storeKeeper = User::factory()->create(['name' => 'Store User']);
        $storeKeeper->assignRole('store-keeper');
        $this->actingAs($storeKeeper)->get('/dashboard')->assertSee('Store Dashboard')->assertSee('Supply chain workspace');
        $this->actingAs($storeKeeper)->get('/inventory')->assertOk();

        $manager = User::factory()->create(['name' => 'Hospital Manager User']);
        $manager->assignRole('hospital-manager');
        $this->actingAs($manager)->get('/dashboard')->assertSee('Hospital Manager Dashboard')->assertSee('Operations workspace');
        $this->actingAs($manager)->get('/users')->assertForbidden();
        $this->actingAs($manager)->get('/audit-logs')->assertForbidden();
        $this->actingAs($manager)->get('/patients/create')->assertForbidden();
        $this->actingAs($manager)->get('/access-matrix')->assertForbidden();

        $admin = User::factory()->create(['name' => 'Super Admin User']);
        $admin->assignRole('super-admin');
        $this->actingAs($admin)->get('/access-matrix')->assertOk()->assertSee('Role access matrix')->assertSee('Hospital manager');

        $receptionist = User::factory()->create(['name' => 'Receptionist User']);
        $receptionist->assignRole('receptionist');
        $this->actingAs($receptionist)->get('/dashboard')->assertSee('Reception Dashboard')->assertSee('Front desk workspace');
        $this->actingAs($receptionist)->get('/appointments')->assertOk();

        $hr = User::factory()->create(['name' => 'HR User']);
        $hr->assignRole('hr');
        $this->actingAs($hr)->get('/dashboard')->assertSee('HR Dashboard')->assertSee('People & payroll workspace');
        $this->actingAs($hr)->get('/hr')->assertOk()->assertSee('Hospital operations')->assertSee('People operations');

        $radiologist = User::factory()->create(['name' => 'Radiologist User']);
        $radiologist->assignRole('radiologist');
        $this->actingAs($radiologist)->get('/dashboard')->assertSee('Radiology Dashboard')->assertSee('Imaging workspace');
        $this->actingAs($radiologist)->get('/radiology')->assertOk();
    }

    public function test_access_matrix_groups_role_linkage_by_department(): void
    {
        foreach (['super-admin', 'hospital-manager', 'doctor', 'nurse', 'lab-technician', 'radiologist', 'pharmacist', 'cashier', 'receptionist', 'hr', 'store-keeper'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $admin = User::factory()->create(['name' => 'Access Matrix Admin']);
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get('/access-matrix')
            ->assertOk()
            ->assertSee('Department role linkage')
            ->assertSee('Clinical')
            ->assertSee('Doctor')
            ->assertSee('Nurse')
            ->assertSee('Diagnostics')
            ->assertSee('Lab technician')
            ->assertSee('Radiologist')
            ->assertSee('Pharmacy')
            ->assertSee('Pharmacist')
            ->assertSee('Finance')
            ->assertSee('Cashier');
    }

    public function test_staff_accounts_store_and_display_a_department(): void
    {
        foreach (['super-admin', 'doctor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $doctor = User::factory()->create(['name' => 'Doctor Wing', 'department' => 'clinical']);
        $doctor->assignRole('doctor');

        $this->actingAs(User::factory()->create()->assignRole('super-admin'))
            ->get('/users')
            ->assertOk()
            ->assertSee('Doctor Wing')
            ->assertSee('Clinical');

        $this->assertDatabaseHas('users', ['email' => $doctor->email, 'department' => 'clinical']);
    }

    public function test_department_mismatch_blocks_access_to_other_department_routes(): void
    {
        foreach (['super-admin', 'doctor', 'lab-technician'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $doctor = User::factory()->create(['name' => 'Wrong Department Doctor', 'department' => 'finance']);
        $doctor->assignRole('doctor');
        $this->actingAs($doctor)->get('/visits')->assertForbidden();

        $labTech = User::factory()->create(['name' => 'Wrong Department Lab Tech', 'department' => 'clinical']);
        $labTech->assignRole('lab-technician');
        $this->actingAs($labTech)->get('/lab')->assertForbidden();
    }

    public function test_every_dashboard_shows_unread_notification_count(): void
    {
        foreach (['super-admin', 'hospital-manager', 'doctor', 'nurse', 'patient'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $doctor = User::factory()->create(['name' => 'Doctor Notifications User']);
        $doctor->assignRole('doctor');

        \App\Models\Notification::create(['user_id' => $doctor->id, 'title' => 'Lab result ready', 'message' => 'Check your result', 'type' => 'lab_result']);
        \App\Models\Notification::create(['user_id' => $doctor->id, 'title' => 'Radiology report ready', 'message' => 'Review imaging', 'type' => 'radiology_result']);
        \App\Models\Notification::create(['user_id' => $doctor->id, 'title' => 'Old message', 'message' => 'Already read', 'type' => 'info', 'read_at' => now()->subDay()]);

        $this->actingAs($doctor)
            ->get('/dashboard')
            ->assertSee('Unread notifications')
            ->assertSee('2');
    }

    public function test_hospital_manager_dashboard_exposes_zone_and_mortality_summary(): void
    {
        foreach (['super-admin', 'hospital-manager', 'doctor', 'nurse'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $manager = User::factory()->create();
        $manager->assignRole('hospital-manager');

        \App\Models\Ward::create([
            'name' => 'ICU Ward A',
            'zone' => 'ICU',
            'ward_type' => 'critical-care',
            'gender_group' => 'mixed',
            'capacity' => 5,
            'status' => 'active',
        ]);

        \App\Models\Ward::create([
            'name' => 'Labour Ward A',
            'zone' => 'Labour & Maternity',
            'ward_type' => 'maternity',
            'gender_group' => 'women',
            'capacity' => 8,
            'status' => 'active',
        ]);

        $patient = \App\Models\Patient::create([
            'patient_number' => 'PT-ORG-1001',
            'first_name' => 'Amina',
            'last_name' => 'Salim',
            'gender' => 'female',
            'date_of_birth' => '1992-01-15',
            'phone' => '+255700000010',
            'address' => 'Dar es Salaam',
            'next_of_kin_name' => 'Salim Family',
            'next_of_kin_phone' => '+255700000011',
            'insurance_provider' => 'NHIF',
        ]);

        \App\Models\DeathRecord::create([
            'patient_id' => $patient->id,
            'date_of_death' => now()->toDateString(),
            'time_of_death' => '18:40',
            'cause' => 'Severe haemorrhage',
            'location' => 'Labour & Maternity',
            'doctor_name' => 'Dr. Nyerere',
            'notes' => 'Patient was transferred after emergency delivery.',
        ]);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertSee('Clinical zone overview')
            ->assertSee('Mortality overview')
            ->assertSee('Clinical zones')
            ->assertSee('Death records')
            ->assertSee('ICU')
            ->assertSee('Labour & Maternity')
            ->assertSee('Deaths total');
    }

    public function test_hospital_dashboard_shows_live_clinical_operations_summary(): void
    {
        foreach (['super-admin', 'hospital-manager', 'doctor', 'nurse'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $manager = User::factory()->create();
        $manager->assignRole('hospital-manager');

        $ward = \App\Models\Ward::create([
            'name' => 'ICU Observation',
            'zone' => 'ICU',
            'ward_type' => 'critical-care',
            'gender_group' => 'mixed',
            'capacity' => 5,
            'status' => 'active',
        ]);

        $patient = \App\Models\Patient::create([
            'patient_number' => 'PT-OPS-2001',
            'first_name' => 'Asha',
            'last_name' => 'Malima',
            'gender' => 'female',
            'date_of_birth' => '1985-11-12',
            'phone' => '+255700000030',
            'address' => 'Mbeya',
            'next_of_kin_name' => 'Malima Family',
            'next_of_kin_phone' => '+255700000031',
            'insurance_provider' => 'NHIF',
        ]);

        \App\Models\Admission::create([
            'patient_id' => $patient->id,
            'ward_id' => $ward->id,
            'admission_number' => 'ADM-OPS-001',
            'admission_date' => now(),
            'status' => 'admitted',
            'reason' => 'Post-op monitoring',
        ]);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertSee('Clinical operations overview')
            ->assertSee('Occupancy')
            ->assertSee('ICU')
            ->assertSee('20%');
    }

    public function test_hospital_dashboard_shows_priority_department_alerts(): void
    {
        foreach (['super-admin', 'hospital-manager', 'doctor', 'nurse'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $manager = User::factory()->create();
        $manager->assignRole('hospital-manager');

        $ward = \App\Models\Ward::create([
            'name' => 'ICU Critical',
            'zone' => 'ICU',
            'ward_type' => 'critical-care',
            'gender_group' => 'mixed',
            'capacity' => 2,
            'status' => 'active',
        ]);

        $patientOne = \App\Models\Patient::create([
            'patient_number' => 'PT-ALERT-001',
            'first_name' => 'Noah',
            'last_name' => 'Temba',
            'gender' => 'male',
            'date_of_birth' => '1984-02-02',
            'phone' => '+255700000040',
            'address' => 'Morogoro',
            'next_of_kin_name' => 'Temba Family',
            'next_of_kin_phone' => '+255700000041',
            'insurance_provider' => 'NHIF',
        ]);

        $patientTwo = \App\Models\Patient::create([
            'patient_number' => 'PT-ALERT-002',
            'first_name' => 'Lucy',
            'last_name' => 'Juma',
            'gender' => 'female',
            'date_of_birth' => '1991-08-14',
            'phone' => '+255700000042',
            'address' => 'Dodoma',
            'next_of_kin_name' => 'Juma Family',
            'next_of_kin_phone' => '+255700000043',
            'insurance_provider' => 'NHIF',
        ]);

        \App\Models\Admission::create([
            'patient_id' => $patientOne->id,
            'ward_id' => $ward->id,
            'admission_number' => 'ADM-ALERT-001',
            'admission_date' => now(),
            'status' => 'admitted',
            'reason' => 'Post-operative monitoring',
        ]);

        \App\Models\Admission::create([
            'patient_id' => $patientTwo->id,
            'ward_id' => $ward->id,
            'admission_number' => 'ADM-ALERT-002',
            'admission_date' => now(),
            'status' => 'admitted',
            'reason' => 'High dependency monitoring',
        ]);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertSee('High-risk units')
            ->assertSee('Priority action')
            ->assertSee('ICU')
            ->assertSee('Critical');
    }

    public function test_hospital_dashboard_shows_recent_admissions_watchlist(): void
    {
        foreach (['super-admin', 'hospital-manager', 'doctor', 'nurse'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $manager = User::factory()->create();
        $manager->assignRole('hospital-manager');

        $ward = \App\Models\Ward::create([
            'name' => 'Maternity High Risk',
            'zone' => 'Labour & Maternity',
            'ward_type' => 'maternity',
            'gender_group' => 'women',
            'capacity' => 5,
            'status' => 'active',
        ]);

        $patient = \App\Models\Patient::create([
            'patient_number' => 'PT-WATCH-001',
            'first_name' => 'Mariam',
            'last_name' => 'Ali',
            'gender' => 'female',
            'date_of_birth' => '1994-07-30',
            'phone' => '+255700000050',
            'address' => 'Kilimanjaro',
            'next_of_kin_name' => 'Ali Family',
            'next_of_kin_phone' => '+255700000051',
            'insurance_provider' => 'NHIF',
        ]);

        \App\Models\Admission::create([
            'patient_id' => $patient->id,
            'ward_id' => $ward->id,
            'admission_number' => 'ADM-WATCH-001',
            'admission_date' => now(),
            'status' => 'admitted',
            'reason' => 'Labour monitoring',
        ]);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertSee('Recent admissions watchlist')
            ->assertSee('Mariam Ali')
            ->assertSee('Labour monitoring');
    }

    public function test_hospital_dashboard_shows_operational_brief(): void
    {
        foreach (['super-admin', 'hospital-manager', 'doctor', 'nurse'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $manager = User::factory()->create();
        $manager->assignRole('hospital-manager');

        $ward = \App\Models\Ward::create([
            'name' => 'ICU Neonatal',
            'zone' => 'ICU',
            'ward_type' => 'critical-care',
            'gender_group' => 'mixed',
            'capacity' => 4,
            'status' => 'active',
        ]);

        $patient = \App\Models\Patient::create([
            'patient_number' => 'PT-BRIEF-001',
            'first_name' => 'Hassan',
            'last_name' => 'Mpepo',
            'gender' => 'male',
            'date_of_birth' => '2009-01-10',
            'phone' => '+255700000060',
            'address' => 'Tabora',
            'next_of_kin_name' => 'Mpepo Family',
            'next_of_kin_phone' => '+255700000061',
            'insurance_provider' => 'NHIF',
        ]);

        \App\Models\Admission::create([
            'patient_id' => $patient->id,
            'ward_id' => $ward->id,
            'admission_number' => 'ADM-BRIEF-001',
            'admission_date' => now(),
            'status' => 'admitted',
            'reason' => 'Severe respiratory monitoring',
        ]);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertSee('Operational brief')
            ->assertSee('Average zone occupancy')
            ->assertSee('Highest pressure zone')
            ->assertSee('ICU');
    }

    public function test_hospital_dashboard_shows_department_trend_overview(): void
    {
        foreach (['super-admin', 'hospital-manager', 'doctor', 'nurse'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $manager = User::factory()->create();
        $manager->assignRole('hospital-manager');

        $ward = \App\Models\Ward::create([
            'name' => 'ICU Trend Unit',
            'zone' => 'ICU',
            'ward_type' => 'critical-care',
            'gender_group' => 'mixed',
            'capacity' => 6,
            'status' => 'active',
        ]);

        $patient = \App\Models\Patient::create([
            'patient_number' => 'PT-TREND-001',
            'first_name' => 'John',
            'last_name' => 'Ndiwa',
            'gender' => 'male',
            'date_of_birth' => '2002-05-17',
            'phone' => '+255700000070',
            'address' => 'Iringa',
            'next_of_kin_name' => 'Ndiwa Family',
            'next_of_kin_phone' => '+255700000071',
            'insurance_provider' => 'NHIF',
        ]);

        \App\Models\Admission::create([
            'patient_id' => $patient->id,
            'ward_id' => $ward->id,
            'admission_number' => 'ADM-TREND-001',
            'admission_date' => now(),
            'status' => 'admitted',
            'reason' => 'Trend monitoring',
        ]);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertSee('Department trend overview')
            ->assertSee('7-day admissions')
            ->assertSee('ICU');
    }

    public function test_hospital_dashboard_shows_performance_scorecard(): void
    {
        foreach (['super-admin', 'hospital-manager', 'doctor', 'nurse'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $manager = User::factory()->create();
        $manager->assignRole('hospital-manager');

        $ward = \App\Models\Ward::create([
            'name' => 'General Ward Scorecard',
            'zone' => 'ICU',
            'ward_type' => 'general',
            'gender_group' => 'mixed',
            'capacity' => 8,
            'status' => 'active',
        ]);

        $patient = \App\Models\Patient::create([
            'patient_number' => 'PT-SCORE-001',
            'first_name' => 'Khadija',
            'last_name' => 'Mongi',
            'gender' => 'female',
            'date_of_birth' => '1997-10-11',
            'phone' => '+255700000080',
            'address' => 'Njombe',
            'next_of_kin_name' => 'Mongi Family',
            'next_of_kin_phone' => '+255700000081',
            'insurance_provider' => 'NHIF',
        ]);

        \App\Models\Admission::create([
            'patient_id' => $patient->id,
            'ward_id' => $ward->id,
            'admission_number' => 'ADM-SCORE-001',
            'admission_date' => now()->subDays(4),
            'discharged_at' => now(),
            'status' => 'discharged',
            'reason' => 'Recovery observation',
        ]);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertSee('Performance scorecard')
            ->assertSee('Average length of stay')
            ->assertSee('Discharge rate')
            ->assertSee('ICU');
    }

    public function test_hospital_dashboard_shows_executive_board_summary(): void
    {
        foreach (['super-admin', 'hospital-manager', 'doctor', 'nurse'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $manager = User::factory()->create();
        $manager->assignRole('hospital-manager');

        $ward = \App\Models\Ward::create([
            'name' => 'Executive Board ICU',
            'zone' => 'ICU',
            'ward_type' => 'critical-care',
            'gender_group' => 'mixed',
            'capacity' => 4,
            'status' => 'active',
        ]);

        $patient = \App\Models\Patient::create([
            'patient_number' => 'PT-BOARD-001',
            'first_name' => 'Moses',
            'last_name' => 'Kalinga',
            'gender' => 'male',
            'date_of_birth' => '1982-06-09',
            'phone' => '+255700000090',
            'address' => 'Sumbawanga',
            'next_of_kin_name' => 'Kalinga Family',
            'next_of_kin_phone' => '+255700000091',
            'insurance_provider' => 'NHIF',
        ]);

        \App\Models\Admission::create([
            'patient_id' => $patient->id,
            'ward_id' => $ward->id,
            'admission_number' => 'ADM-BOARD-001',
            'admission_date' => now(),
            'status' => 'admitted',
            'reason' => 'Board review case',
        ]);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertSee('Executive board summary')
            ->assertSee('Bed utilisation')
            ->assertSee('Critical units')
            ->assertSee('ICU');
    }

    public function test_hospital_dashboard_shows_operational_action_queue(): void
    {
        foreach (['super-admin', 'hospital-manager', 'doctor', 'nurse'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $manager = User::factory()->create();
        $manager->assignRole('hospital-manager');

        $ward = \App\Models\Ward::create([
            'name' => 'Action Queue ICU',
            'zone' => 'ICU',
            'ward_type' => 'critical-care',
            'gender_group' => 'mixed',
            'capacity' => 2,
            'status' => 'active',
        ]);

        $patient = \App\Models\Patient::create([
            'patient_number' => 'PT-ACTION-001',
            'first_name' => 'Salma',
            'last_name' => 'Mfori',
            'gender' => 'female',
            'date_of_birth' => '1990-09-20',
            'phone' => '+255700000100',
            'address' => 'Tanga',
            'next_of_kin_name' => 'Mfori Family',
            'next_of_kin_phone' => '+255700000101',
            'insurance_provider' => 'NHIF',
        ]);

        \App\Models\Admission::create([
            'patient_id' => $patient->id,
            'ward_id' => $ward->id,
            'admission_number' => 'ADM-ACTION-001',
            'admission_date' => now(),
            'status' => 'admitted',
            'reason' => 'Immediate ICU review',
        ]);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertSee('Operational action queue')
            ->assertSee('Immediate staffing review')
            ->assertSee('ICU');
    }

    public function test_reports_show_capacity_by_zone_and_death_cause_breakdown(): void
    {
        foreach (['super-admin', 'hospital-manager', 'doctor', 'nurse'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $manager = User::factory()->create();
        $manager->assignRole('hospital-manager');

        $wardIcu = \App\Models\Ward::create([
            'name' => 'ICU Ward B',
            'zone' => 'ICU',
            'ward_type' => 'critical-care',
            'gender_group' => 'mixed',
            'capacity' => 4,
            'status' => 'active',
        ]);

        $wardMaternity = \App\Models\Ward::create([
            'name' => 'Labour Ward B',
            'zone' => 'Labour & Maternity',
            'ward_type' => 'maternity',
            'gender_group' => 'women',
            'capacity' => 6,
            'status' => 'active',
        ]);

        $patientOne = \App\Models\Patient::create([
            'patient_number' => 'PT-REP-1001',
            'first_name' => 'Grace',
            'last_name' => 'Kibona',
            'gender' => 'female',
            'date_of_birth' => '1988-03-10',
            'phone' => '+255700000020',
            'address' => 'Dodoma',
            'next_of_kin_name' => 'Kibona Family',
            'next_of_kin_phone' => '+255700000021',
            'insurance_provider' => 'NHIF',
        ]);

        $patientTwo = \App\Models\Patient::create([
            'patient_number' => 'PT-REP-1002',
            'first_name' => 'Zuberi',
            'last_name' => 'Maro',
            'gender' => 'male',
            'date_of_birth' => '1977-07-27',
            'phone' => '+255700000022',
            'address' => 'Mwanza',
            'next_of_kin_name' => 'Maro Family',
            'next_of_kin_phone' => '+255700000023',
            'insurance_provider' => 'NHIF',
        ]);

        \App\Models\DeathRecord::create([
            'patient_id' => $patientOne->id,
            'date_of_death' => now()->toDateString(),
            'time_of_death' => '12:10',
            'cause' => 'Severe haemorrhage',
            'location' => 'Labour & Maternity',
            'doctor_name' => 'Dr. Asha',
            'notes' => 'Postpartum bleeding.',
        ]);

        \App\Models\DeathRecord::create([
            'patient_id' => $patientTwo->id,
            'date_of_death' => now()->toDateString(),
            'time_of_death' => '14:45',
            'cause' => 'Cardiac arrest',
            'location' => 'ICU',
            'doctor_name' => 'Dr. Asha',
            'notes' => 'Cardiopulmonary collapse.',
        ]);

        \App\Models\Admission::create([
            'patient_id' => $patientOne->id,
            'ward_id' => $wardIcu->id,
            'admission_number' => 'ADM-REP-001',
            'admission_date' => now(),
            'status' => 'admitted',
            'reason' => 'Critical monitoring',
        ]);

        \App\Models\Admission::create([
            'patient_id' => $patientTwo->id,
            'ward_id' => $wardMaternity->id,
            'admission_date' => now(),
            'status' => 'admitted',
            'reason' => 'Labour observation',
            'admission_number' => 'ADM-REP-002',
        ]);

        $this->actingAs($manager)->get('/reports')
            ->assertSee('Capacity by clinical zone')
            ->assertSee('Cause breakdown')
            ->assertSee('ICU')
            ->assertSee('Severe haemorrhage')
            ->assertSee('Cardiac arrest');
    }

    public function test_role_boundaries_block_cross_department_actions(): void
    {
        foreach (['super-admin', 'doctor', 'nurse', 'patient', 'cashier', 'lab-technician', 'radiologist'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');

        $patient = \App\Models\Patient::create([
            'patient_number' => 'PT-ROLE-001',
            'first_name' => 'Role',
            'last_name' => 'Boundary',
            'gender' => 'male',
            'date_of_birth' => '2000-01-01',
            'phone' => '+255700000001',
            'address' => 'Arusha',
            'next_of_kin_name' => 'Guardian',
            'next_of_kin_phone' => '+255700000002',
            'insurance_provider' => 'NHIF',
        ]);
        $visit = \App\Models\Visit::create([
            'patient_id' => $patient->id,
            'visit_number' => 'VIS-ROLE-001',
            'visit_date' => now(),
            'department' => 'OPD',
            'doctor_id' => $doctor->id,
            'complaint' => 'Fever',
            'diagnosis' => 'Flu',
            'status' => 'completed',
        ]);

        $nurse = User::factory()->create();
        $nurse->assignRole('nurse');
        $this->actingAs($nurse)->post('/visits/'.$visit->id.'/vitals', ['temperature' => 38.2])->assertForbidden();

        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $this->actingAs($doctor)->get('/billing')->assertForbidden();

        $patientUser = User::factory()->create();
        $patientUser->assignRole('patient');
        $this->actingAs($patientUser)->get('/patients')->assertForbidden();
        $this->actingAs($patientUser)->get('/portal')->assertOk();

        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');
        $this->actingAs($cashier)->get('/lab')->assertForbidden();
    }
}
