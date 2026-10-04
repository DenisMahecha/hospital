<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Patient;
use App\Models\PatientPortalAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HmsUserAdministrationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_edit_and_toggle_another_user_account(): void
    {
        Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::create(['name' => 'nurse', 'guard_name' => 'web']);
        Role::create(['name' => 'doctor', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $user = User::factory()->create(['name' => 'Old Name', 'is_active' => true]);
        $user->assignRole('nurse');

        $this->actingAs($admin)->get("/users/{$user->id}/edit")->assertOk()->assertSee('Edit user');
        $this->actingAs($admin)->put("/users/{$user->id}", ['name' => 'Updated Name', 'email' => $user->email, 'phone' => '+255700000001', 'role' => 'doctor'])->assertRedirect('/users');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name', 'phone' => '+255700000001']);
        $this->assertTrue($user->fresh()->hasRole('doctor'));

        $this->actingAs($admin)->post("/users/{$user->id}/toggle-status")->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
        $this->actingAs($admin)->post("/users/{$user->id}/toggle-status")->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => true]);
    }

    public function test_super_admin_cannot_deactivate_their_own_account(): void
    {
        Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::create(['name' => 'patient', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $this->actingAs($admin)->post("/users/{$admin->id}/toggle-status")->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => true]);
    }

    public function test_super_admin_can_create_a_patient_portal_account_for_a_registered_patient(): void
    {
        Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::create(['name' => 'patient', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $patient = Patient::create(['patient_number' => 'PAT-PORTAL-ADMIN', 'first_name' => 'Portal', 'last_name' => 'Patient']);

        $this->actingAs($admin)->get('/users')->assertOk()->assertDontSee('Create patient account');
        $this->actingAs($admin)->get('/portal')->assertOk()->assertSee('Patient portal accounts')->assertSee('Create patient account');
        $this->actingAs($admin)->post('/users/patient-portal', ['patient_id' => $patient->id, 'email' => 'portal.patient@hospital.test', 'portal_username' => 'portal.patient', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertRedirect();

        $portalUser = User::where('email', 'portal.patient@hospital.test')->firstOrFail();
        $this->assertTrue($portalUser->hasRole('patient'));
        $this->assertDatabaseHas('patient_portal_accounts', ['patient_id' => $patient->id, 'user_id' => $portalUser->id, 'portal_username' => 'portal.patient', 'status' => 'active']);
    }

    public function test_admin_can_link_and_unlink_an_existing_patient_portal_account(): void
    {
        Role::create(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::create(['name' => 'patient', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $patient = Patient::create(['patient_number' => 'PAT-PORTAL-TOGGLE', 'first_name' => 'Toggle', 'last_name' => 'Patient']);
        $portalUser = User::factory()->create(['email' => 'toggle.patient@hospital.test', 'password' => Hash::make('secret123')]);
        $portalUser->assignRole('patient');
        $account = PatientPortalAccount::create(['patient_id' => $patient->id, 'user_id' => $portalUser->id, 'portal_username' => 'toggle.patient', 'status' => 'active']);

        $this->actingAs($admin)->post("/portal/{$account->id}/toggle-link")->assertRedirect();
        $this->assertDatabaseHas('patient_portal_accounts', ['id' => $account->id, 'status' => 'disabled']);
        $this->actingAs($admin)->post("/portal/{$account->id}/toggle-link")->assertRedirect();
        $this->assertDatabaseHas('patient_portal_accounts', ['id' => $account->id, 'status' => 'active']);
    }

    public function test_super_admin_can_provision_users_for_other_hospital_roles(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'nurse', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        $this->actingAs($admin)->get('/users')->assertOk()->assertSee('Provision account');
        $this->actingAs($admin)->get('/users/create')->assertOk()->assertSee('New staff account')->assertSee('Create user account');
        $this->actingAs($admin)->post('/users', [
            'name' => 'Nurse Grace',
            'email' => 'grace.nurse@hospital.test',
            'phone' => '+255700111222',
            'role' => 'nurse',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertRedirect('/users');

        $user = User::where('email', 'grace.nurse@hospital.test')->firstOrFail();
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertTrue($user->hasRole('nurse'));
        $this->assertTrue($user->is_active);
    }

    public function test_hospital_manager_cannot_assign_super_admin(): void
    {
        Role::firstOrCreate(['name' => 'hospital-manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $manager = User::factory()->create();
        $manager->assignRole('hospital-manager');

        $this->actingAs($manager)
            ->post('/users', [
                'name' => 'Escalated User',
                'email' => 'escalated@hospital.test',
                'role' => 'super-admin',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'escalated@hospital.test']);
    }
}
