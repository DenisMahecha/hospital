<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_to_system_department_or_specific_user(): void
    {
        foreach (['super-admin', 'doctor', 'nurse', 'cashier'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');
        $doctor = User::factory()->create();
        $doctor->assignRole('doctor');
        $nurse = User::factory()->create();
        $nurse->assignRole('nurse');
        $cashier = User::factory()->create();
        $cashier->assignRole('cashier');

        $this->actingAs($admin)->get('/notifications')->assertOk()->assertSee('Send notification')->assertSee('Whole system');
        $this->actingAs($doctor)->get('/notifications')->assertOk()->assertDontSee('Send notification');

        $this->actingAs($admin)->post('/notifications', ['target_type' => 'department', 'department_target' => 'clinical', 'type' => 'urgent', 'title' => 'Clinical briefing', 'message' => 'Attend the briefing.'])->assertRedirect();
        $this->assertDatabaseHas('notifications', ['user_id' => $doctor->id, 'title' => 'Clinical briefing']);
        $this->assertDatabaseHas('notifications', ['user_id' => $nurse->id, 'title' => 'Clinical briefing']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $cashier->id, 'title' => 'Clinical briefing']);

        $this->actingAs($admin)->post('/notifications', ['target_type' => 'user', 'user_target' => $cashier->id, 'type' => 'info', 'title' => 'Private note', 'message' => 'For cashier only.'])->assertRedirect();
        $this->assertDatabaseHas('notifications', ['user_id' => $cashier->id, 'title' => 'Private note']);
    }
}