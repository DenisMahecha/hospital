<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientPortalAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TwoFactorCodeNotification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HmsAuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_and_staff_can_sign_in(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');

        Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'doctor@hospital.test',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $user->assignRole('doctor');

        $this->post('/login', [
            'email' => 'doctor@hospital.test',
            'password' => 'secret123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'login',
            'module_name' => 'authentication',
        ]);
    }

    public function test_disabled_patient_portal_account_cannot_sign_in(): void
    {
        Role::firstOrCreate(['name' => 'patient', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'patient@hospital.test',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $user->assignRole('patient');

        $patient = Patient::create([
            'patient_number' => 'PT-AUTH-1',
            'first_name' => 'Portal',
            'last_name' => 'Patient',
        ]);

        PatientPortalAccount::create([
            'patient_id' => $patient->id,
            'user_id' => $user->id,
            'portal_username' => 'portal.patient',
            'status' => 'disabled',
        ]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'patient@hospital.test',
                'password' => 'secret123',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_sign_out_and_event_is_audited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'logout',
            'module_name' => 'authentication',
        ]);
    }

    public function test_doctor_cannot_access_inventory(): void
    {
        Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');

        $this->actingAs($doctor)
            ->get('/inventory')
            ->assertForbidden();
    }

    public function test_login_attempts_are_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/login')->post('/login', ['email' => 'bruteforce@hospital.test', 'password' => 'wrong-password'])
                ->assertRedirect('/login');
        }

        $this->from('/login')->post('/login', ['email' => 'bruteforce@hospital.test', 'password' => 'wrong-password'])
            ->assertStatus(429);
    }

    public function test_enabled_two_factor_requires_email_code(): void
    {
        Notification::fake();
        Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        $user = User::factory()->create(['email' => 'secure-doctor@hospital.test', 'password' => Hash::make('secret123'), 'two_factor_enabled' => true]);
        $user->assignRole('doctor');

        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])->assertRedirect('/login/2fa');
        $this->assertGuest();
        Notification::assertSentTo($user, TwoFactorCodeNotification::class);
    }

    public function test_audit_logs_cannot_be_deleted(): void
    {
        $log = AuditLog::create(['action' => 'test', 'module_name' => 'security', 'description' => 'Immutable']);
        $log->delete();

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }
}
