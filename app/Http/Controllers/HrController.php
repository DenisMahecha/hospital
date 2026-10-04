<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use App\Services\DailyNumberService;

class HrController extends Controller
{
    public function index(): View
    {
        return view('hr.index', [
            'employees' => Employee::latest()->paginate(25),
            'employeeOptions' => Employee::orderBy('first_name')->orderBy('last_name')->get(),
            'payroll' => Payroll::latest()->take(10)->get(),
            'employeeRoles' => ['Doctor', 'Nurse', 'Lab Technician', 'Radiologist', 'Pharmacist', 'Cashier', 'Receptionist', 'HR Officer', 'Store Keeper', 'Hospital Manager', 'Administrator'],
            'departments' => ['Administration', 'OPD', 'IPD / Wards', 'Emergency', 'Laboratory', 'Radiology', 'Pharmacy', 'Finance', 'Human Resources', 'Stores & Procurement', 'Theatre', 'RCH'],
        ]);
    }

    public function storeEmployee(Request $request, DailyNumberService $numberService): RedirectResponse
    {
        $data = $request->validate(['first_name' => ['required', 'string'], 'last_name' => ['required', 'string'], 'role' => ['required', Rule::in(['Doctor', 'Nurse', 'Lab Technician', 'Radiologist', 'Pharmacist', 'Cashier', 'Receptionist', 'HR Officer', 'Store Keeper', 'Hospital Manager', 'Administrator'])], 'department' => ['required', Rule::in(['Administration', 'OPD', 'IPD / Wards', 'Emergency', 'Laboratory', 'Radiology', 'Pharmacy', 'Finance', 'Human Resources', 'Stores & Procurement', 'Theatre', 'RCH'])]]);
        $data['employee_number'] = $numberService->next('employee', 'EMP');
        $data['status'] = 'active';
        $employee = Employee::create($data);
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'hr', 'model_type' => Employee::class, 'model_id' => $employee->id, 'description' => 'Employee record created.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('success', 'Employee added.');
    }

    public function storePayroll(Request $request): RedirectResponse
    {
        $data = $request->validate(['employee_id' => ['required', 'exists:employees,id'], 'month' => ['required', 'date_format:Y-m'], 'basic_salary' => ['required', 'numeric', 'min:0'], 'allowances' => ['nullable', 'numeric', 'min:0'], 'deductions' => ['nullable', 'numeric', 'min:0']]);
        $data['net_salary'] = (float) $data['basic_salary'] + (float) ($data['allowances'] ?? 0) - (float) ($data['deductions'] ?? 0);
        $data['status'] = 'draft';
        $payroll = Payroll::updateOrCreate(['employee_id' => $data['employee_id'], 'month' => $data['month']], $data);
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'hr', 'model_type' => Payroll::class, 'model_id' => $payroll->id, 'description' => 'Payroll entry prepared.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('success', 'Payroll entry saved.');
    }
}
