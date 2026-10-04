<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class HmsPasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_request_page_and_notification_work(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@hospital.test']);

        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect('/forgot-password');

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
