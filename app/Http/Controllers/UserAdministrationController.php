<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientPortalAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserAdministrationController extends Controller
{
    public function index(): View
    {
        $roles = Role::query()->when(auth()->user()->hasRole('hospital-manager'), fn ($query) => $query->whereNotIn('name', ['super-admin', 'hospital-manager']))->orderBy('name')->get();

        return view('users.index', ['users' => User::with('roles', 'patientPortalAccounts.patient')->latest()->paginate(25), 'roles' => $roles, 'patients' => Patient::with('portalAccounts')->orderBy('first_name')->get()]);
    }

    public function create(): View
    {
        return view('users.create', ['roles' => Role::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'department' => ['required', 'string', Rule::in(array_keys(config('department_roles.mapping')))],
            'role' => ['required', 'exists:roles,name'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $role = $data['role'];
        $department = $data['department'];
        abort_if($request->user()->hasRole('hospital-manager') && in_array($role, ['super-admin', 'hospital-manager'], true), 403);
        abort_unless(in_array($role, config('department_roles.mapping')[$department] ?? [], true), 422, 'The selected role does not match the selected department.');
        unset($data['role'], $data['password_confirmation']);
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        $user->assignRole($role);

        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'users', 'model_type' => User::class, 'model_id' => $user->id, 'description' => 'Created user with role '.$role, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return redirect()->route('users.index')->with('success', 'User created and role assigned successfully.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', ['user' => $user->load('roles'), 'roles' => Role::orderBy('name')->get()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'department' => ['required', 'string', Rule::in(array_keys(config('department_roles.mapping')))],
            'role' => ['required', 'exists:roles,name'],
            'password' => ['nullable', 'confirmed', 'min:8'],
        ]);
        $role = $data['role'];
        $department = $data['department'];
        abort_unless(in_array($role, config('department_roles.mapping')[$department] ?? [], true), 422, 'The selected role does not match the selected department.');
        unset($data['role'], $data['password_confirmation']);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }
        $user->update($data);
        $user->syncRoles($role);
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'update', 'module_name' => 'users', 'model_type' => User::class, 'model_id' => $user->id, 'description' => 'Updated user profile and role.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return redirect()->route('users.index')->with('success', 'User information updated successfully.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'You cannot deactivate your own account.');
        $user->update(['is_active' => ! $user->is_active]);
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'update', 'module_name' => 'users', 'model_type' => User::class, 'model_id' => $user->id, 'description' => ($user->is_active ? 'Activated' : 'Deactivated').' user account.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('success', $user->is_active ? 'User account activated.' : 'User account deactivated.');
    }

    public function storePatientPortalAccount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'email' => ['required', 'email', 'unique:users,email'],
            'portal_username' => ['required', 'string', 'max:100', 'unique:patient_portal_accounts,portal_username'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        abort_if(PatientPortalAccount::where('patient_id', $data['patient_id'])->where('status', 'active')->exists(), 422, 'This patient already has an active portal account.');
        $patient = Patient::findOrFail($data['patient_id']);
        $user = User::create(['name' => $patient->first_name.' '.$patient->last_name, 'email' => $data['email'], 'password' => Hash::make($data['password']), 'is_active' => true]);
        $user->assignRole('patient');
        $account = PatientPortalAccount::create(['patient_id' => $patient->id, 'user_id' => $user->id, 'portal_username' => $data['portal_username'], 'status' => 'active']);
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'patient-portal', 'model_type' => PatientPortalAccount::class, 'model_id' => $account->id, 'description' => 'Patient portal account created for patient '.$patient->patient_number.'.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return back()->with('success', 'Patient portal account created and activated.');
    }
}
