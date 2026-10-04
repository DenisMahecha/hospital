<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Appointment;
use App\Models\Notification;
use App\Models\PatientPortalAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\DailyNumberService;

class VisitController extends Controller
{
    public function index(): View
    {
        $appointments = Appointment::with('patient')
            ->when(auth()->user()->hasRole('doctor'), fn ($query) => $query->where('doctor_id', auth()->id()))
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->orderBy('scheduled_at')
            ->get();
        $patients = auth()->user()->hasRole('doctor')
            ? Patient::whereIn('id', $appointments->pluck('patient_id'))->orderBy('first_name')->get()
            : Patient::orderBy('first_name')->get();
        return view('visits.index', [
            'visits' => Visit::with('patient', 'vitals')->when(auth()->user()->hasRole('doctor'), fn ($query) => $query->where('doctor_id', auth()->id()))->latest('visit_date')->paginate(25),
            'patients' => $patients,
            'appointments' => $appointments,
        ]);
    }

    public function store(Request $request, DailyNumberService $numberService): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'appointment_id' => $request->user()->hasRole('doctor')
                ? ['required', 'exists:appointments,id']
                : ['nullable', 'exists:appointments,id'],
            'department' => ['required', 'string', 'max:100'],
            'complaint' => ['required', 'string'],
            'diagnosis' => ['nullable', 'string'],
            'icd10_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
            'referral_notes' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
        ]);
        $data['visit_number'] = $numberService->next('visit', 'VIS');
        $data['visit_date'] = now();
        $data['doctor_id'] = $request->user()->id;
        $data['status'] = 'completed';
        if ($request->user()->hasRole('doctor') && ! Appointment::where('doctor_id', $request->user()->id)->where('patient_id', $data['patient_id'])->whereIn('status', ['confirmed', 'checked_in'])->whereKey($data['appointment_id'])->exists()) {
            abort(422, 'You can only consult patients assigned to you.');
        }
        if (! empty($data['appointment_id'])) {
            $appointment = Appointment::whereKey($data['appointment_id'])
                ->where('doctor_id', $request->user()->id)
                ->where('patient_id', $data['patient_id'])
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->firstOrFail();
            $appointment->update(['status' => 'completed']);
        }
        $visit = Visit::create($data);

        if (! empty($data['follow_up_date'])) {
            $portalUserId = PatientPortalAccount::where('patient_id', $visit->patient_id)->where('status', 'active')->value('user_id');
            if ($portalUserId) {
                Notification::create([
                    'user_id' => $portalUserId,
                    'title' => 'Follow-up reminder',
                    'message' => 'Your follow-up visit is recommended for '.$data['follow_up_date'].'.',
                    'type' => 'follow_up',
                    'metadata' => ['visit_id' => $visit->id, 'follow_up_date' => $data['follow_up_date']],
                ]);
            }
        }

        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'visits', 'model_type' => Visit::class, 'model_id' => $visit->id, 'description' => 'Clinical consultation recorded.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return redirect()->route('visits.index')->with('success', 'Consultation recorded successfully.');
    }

    public function storeVitals(Request $request, Visit $visit): RedirectResponse
    {
        abort_if($request->user()->hasRole('doctor') && $visit->doctor_id !== $request->user()->id, 403, 'Doctors can only record vitals for their assigned visits.');
        $vitals = $visit->vitals()->create($request->validate([
            'temperature' => ['nullable', 'numeric', 'between:25,45'],
            'blood_pressure' => ['nullable', 'string', 'max:20'],
            'weight' => ['nullable', 'numeric', 'between:0,500'],
            'heart_rate' => ['nullable', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
        ]) + ['recorded_by' => $request->user()->id]);

        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'vitals', 'model_type' => get_class($vitals), 'model_id' => $vitals->id, 'description' => 'Patient vital signs recorded.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return back()->with('success', 'Vital signs recorded successfully.');
    }
}