<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View { return view('auth.forgot-password'); }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($data);
        $user = User::where('email', $data['email'])->first();

        AuditLog::create(['user_id' => $user?->id, 'action' => $status === Password::RESET_LINK_SENT ? 'password_reset_requested' : 'password_reset_failed', 'module_name' => 'authentication', 'description' => 'Password reset request processed.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return back()->with('status', __($status));
    }

    public function resetForm(string $token): View { return view('auth.reset-password', ['token' => $token, 'email' => request('email')]); }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', 'min:8']]);
        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('success', 'Your password has been reset. You can sign in now.');
    }
}
