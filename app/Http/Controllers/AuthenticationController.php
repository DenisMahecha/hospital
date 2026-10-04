<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\View\View;

class AuthenticationController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()->withErrors(['email' => 'Too many login attempts. Please try again later.'])->setStatusCode(429);
        }

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($throttleKey, 60);
            $failedUser = \App\Models\User::where('email', $credentials['email'])->first();
            if ($failedUser) {
                $this->registerFailedAttempt($failedUser);
            }
            $this->recordLoginFailure($request);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'The provided credentials are invalid.']);
        }

        $user = \App\Models\User::where('email', $credentials['email'])->first();

        if ($user->locked_until?->isFuture()) {
            return back()->withErrors(['email' => 'This account is temporarily locked. Please try again later.'])->setStatusCode(423);
        }

        if (! $user->is_active || ($user->hasRole('patient') && ! $user->patientPortalAccounts()->where('status', 'active')->exists())) {
            $this->registerFailedAttempt($user);
            $this->recordLoginFailure($request, $user->id);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This account is not allowed to sign in.']);
        }

        if ($user->two_factor_enabled || (config('security.require_two_factor') && $user->hasAnyRole(['super-admin', 'doctor']))) {
            $code = (string) random_int(100000, 999999);
            $user->forceFill(['two_factor_code_hash' => Hash::make($code), 'two_factor_expires_at' => now()->addMinutes(10)])->save();
            $request->session()->put(['two_factor_user_id' => $user->id, 'two_factor_remember' => $request->boolean('remember')]);
            $user->notify(new TwoFactorCodeNotification($code));

            return redirect()->route('login.2fa');
        }

        Auth::login($user, $request->boolean('remember'));
        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();
        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $portalAccount = $user->patientPortalAccounts()->where('status', 'active')->first();
        $portalAccount?->update(['last_login_at' => now()]);

        AuditLog::create([
            'user_id' => $user->id,
            'user_type' => $user::class,
            'action' => 'login',
            'module_name' => 'authentication',
            'description' => 'User signed in.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function twoFactorForm(): View
    {
        abort_unless(session()->has('two_factor_user_id'), 403);
        return view('auth.two-factor');
    }

    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = \App\Models\User::findOrFail((int) $request->session()->get('two_factor_user_id'));
        abort_if(! $user->two_factor_expires_at?->isFuture() || ! Hash::check($data['code'], (string) $user->two_factor_code_hash), 422, 'The verification code is invalid or expired.');
        Auth::login($user, (bool) $request->session()->pull('two_factor_remember', false));
        $user->forceFill(['two_factor_code_hash' => null, 'two_factor_expires_at' => null, 'failed_login_attempts' => 0, 'locked_until' => null])->save();
        $request->session()->forget('two_factor_user_id');
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        AuditLog::create([
            'user_id' => $user?->id,
            'user_type' => $user ? $user::class : null,
            'action' => 'logout',
            'module_name' => 'authentication',
            'description' => 'User signed out.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function recordLoginFailure(Request $request, ?int $userId = null): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'action' => 'login_failed',
            'module_name' => 'authentication',
            'description' => 'Failed sign-in attempt.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function registerFailedAttempt(\App\Models\User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;
        $user->update(['failed_login_attempts' => $attempts, 'locked_until' => $attempts >= config('security.login_max_attempts') ? now()->addMinutes(config('security.lockout_minutes')) : null]);
    }

    private function throttleKey(Request $request): string
    {
        return strtolower((string) $request->input('email')).'|'.$request->ip();
    }
}
