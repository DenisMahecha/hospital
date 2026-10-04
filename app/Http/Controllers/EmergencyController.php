<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\AuditLog;
use App\Models\EmergencyCase;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\DailyNumberService;
use App\Services\DoctorPatientScope;

class EmergencyController extends Controller
{
    public function index(Request $request): View
    {
        return view('emergency.index', [
            'triage_count' => Admission::where('status', 'admitted')->count(),
            'urgent_patients' => 0,
            'cases' => EmergencyCase::when($request->user()->hasRole('doctor'), fn ($query) => $query->whereIn('patient_id', app(DoctorPatientScope::class)->patientsFor($request->user())->select('patients.id')))->where('status', 'open')->latest()->get(),
            'patients' => app(DoctorPatientScope::class)->patientsFor($request->user())->orderBy('first_name')->get(),
        ]);
    }

    public function store(Request $request, DailyNumberService $numberService): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['nullable', 'exists:patients,id'],
            'temporary_name' => ['nullable', 'string'],
            'priority' => ['required', 'in:red,yellow,green'],
            'complaint' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
        if ($request->user()->hasRole('doctor') && ! empty($data['patient_id'])) {
            abort_unless(app(DoctorPatientScope::class)->allows($request->user(), (int) $data['patient_id']), 403, 'You can only manage emergency cases for patients assigned to you.');
        }
        $data['case_number'] = $numberService->next('emergency_case', 'ER');
        $data['registered_by'] = $request->user()->id;
        $data['status'] = 'open';
        $case = EmergencyCase::create($data);
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'emergency', 'model_type' => EmergencyCase::class, 'model_id' => $case->id, 'description' => 'Emergency case registered and triaged.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('success', 'Emergency case registered.');
    }

    public function close(Request $request, EmergencyCase $emergencyCase): RedirectResponse
    {
        abort_unless(! $request->user()->hasRole('doctor') || ! $emergencyCase->patient_id || app(DoctorPatientScope::class)->allows($request->user(), (int) $emergencyCase->patient_id), 403, 'You can only close cases for patients assigned to you.');
        $emergencyCase->update(['status' => 'closed']);
        return back()->with('success', 'Emergency case closed.');
    }
}
