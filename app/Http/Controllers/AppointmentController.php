<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use App\Services\DailyNumberService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;
use App\Models\PatientPortalAccount;
use App\Models\User;
use App\Services\DoctorPatientScope;

class AppointmentController extends Controller
{
    public function index(Request $request): View
    {
        $patientSearch = trim((string) $request->query('patient_search', ''));
        $appointmentDate = $request->query('date');
        $patientQuery = app(DoctorPatientScope::class)->patientsFor($request->user())->where('is_active', true);
        $patients = $patientSearch === '' ? collect() : $patientQuery->where(function ($patientQuery) use ($patientSearch): void {
            $patientQuery->where('first_name', 'like', '%'.$patientSearch.'%')->orWhere('last_name', 'like', '%'.$patientSearch.'%')->orWhere('patient_number', 'like', '%'.$patientSearch.'%');
        })->orderBy('first_name')->get();
        $appointments = Appointment::with(['patient', 'doctor'])
            ->when(auth()->user()->hasRole('doctor'), fn ($query) => $query->where('doctor_id', auth()->id()))
            ->when($appointmentDate, fn ($query) => $query->whereDate('scheduled_at', $appointmentDate))
            ->latest('scheduled_at')->paginate(25)->withQueryString();

        return view('appointments.index', [
            'appointments' => $appointments,
            'patients' => $patients,
            'patientSearch' => $patientSearch,
            'appointmentDate' => $appointmentDate,
            'departments' => ['OPD', 'Emergency', 'RCH', 'Cardiology', 'Dental', 'Maternity'],
            'doctors' => User::whereHas('roles', fn ($query) => $query->where('name', 'doctor')->where('guard_name', 'web'))->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function queue(): View
    {
        $appointments = Appointment::with(['patient', 'doctor'])
            ->whereDate('scheduled_at', today())
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in'])
            ->when(auth()->user()->hasRole('doctor'), fn ($query) => $query->where('doctor_id', auth()->id()))
            ->orderBy('scheduled_at')
            ->get();

        return view('appointments.queue', [
            'appointments' => $appointments,
            'waitingCount' => $appointments->whereIn('status', ['scheduled', 'confirmed'])->count(),
            'checkedInCount' => $appointments->where('status', 'checked_in')->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $patientSearch = trim((string) $request->query('patient_search', ''));
        $patientQuery = app(DoctorPatientScope::class)->patientsFor($request->user())->where('is_active', true);
        $patients = $patientSearch === '' ? collect() : $patientQuery->where(function ($query) use ($patientSearch): void {
            $fullName = $query->getConnection()->getDriverName() === 'sqlite'
                ? "first_name || ' ' || last_name"
                : "CONCAT(first_name, ' ', last_name)";
            $query->where('first_name', 'like', '%'.$patientSearch.'%')
                ->orWhere('last_name', 'like', '%'.$patientSearch.'%')
                ->orWhereRaw("TRIM({$fullName}) LIKE ?", ['%'.$patientSearch.'%'])
                ->orWhere('patient_number', 'like', '%'.$patientSearch.'%')
                ->orWhere('phone', 'like', '%'.$patientSearch.'%');
        })->orderBy('first_name')->get();

        return view('appointments.create', [
            'patients' => $patients,
            'patientSearch' => $patientSearch,
            'departments' => ['OPD', 'Emergency', 'RCH', 'Cardiology', 'Dental', 'Maternity'],
            'doctors' => User::whereHas('roles', fn ($query) => $query->where('name', 'doctor')->where('guard_name', 'web'))->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function patientSuggestions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $patients = app(DoctorPatientScope::class)->patientsFor($request->user())->where('is_active', true)->where(function ($query) use ($search): void {
            $fullName = $query->getConnection()->getDriverName() === 'sqlite'
                ? "first_name || ' ' || last_name"
                : "CONCAT(first_name, ' ', last_name)";
            $query->where('first_name', 'like', '%'.$search.'%')
                ->orWhere('last_name', 'like', '%'.$search.'%')
                ->orWhereRaw("TRIM({$fullName}) LIKE ?", ['%'.$search.'%'])
                ->orWhere('patient_number', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%');
        })->orderBy('first_name')->limit(10)->get(['id', 'first_name', 'last_name', 'patient_number', 'phone']);

        return response()->json($patients->map(fn (Patient $patient): array => [
            'id' => $patient->id,
            'label' => trim($patient->first_name.' '.$patient->last_name).' - '.$patient->patient_number,
            'name' => trim($patient->first_name.' '.$patient->last_name),
        ])->values());
    }

    public function store(Request $request, DailyNumberService $numberService): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'doctor_id' => ['nullable', 'exists:users,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'department' => ['required', 'in:OPD,Emergency,RCH,Cardiology,Dental,Maternity'],
            'reason' => ['nullable', 'string'],
        ]);

        $patient = Patient::findOrFail($data['patient_id']);
        abort_unless(! $request->user()->hasRole('doctor') || app(DoctorPatientScope::class)->allows($request->user(), (int) $data['patient_id']), 403, 'You can only book appointments for patients assigned to you.');
        if ($request->user()->hasRole('doctor')) {
            $data['doctor_id'] = $request->user()->id;
        }
        if (! empty($data['doctor_id'])) {
            abort_unless(User::whereHas('roles', fn ($query) => $query->where('name', 'doctor')->where('guard_name', 'web'))->whereKey($data['doctor_id'])->where('is_active', true)->exists(), 422, 'The selected user is not an active doctor.');
        }
        abort_if(! $patient->is_active, 422, 'Appointments cannot be booked for an inactive patient.');
        $scheduledAt = Carbon::parse($data['scheduled_at']);
        $conflict = Appointment::where('scheduled_at', $scheduledAt)
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in'])
            ->where(function ($query) use ($data): void {
                $query->where('patient_id', $data['patient_id'])
                    ->orWhere(function ($departmentQuery) use ($data): void {
                        $departmentQuery->where('department', $data['department'])
                            ->where(function ($doctorQuery) use ($data): void {
                                if (! empty($data['doctor_id'])) {
                                    $doctorQuery->where('doctor_id', $data['doctor_id']);
                                } else {
                                    $doctorQuery->whereNull('doctor_id');
                                }
                            });
                    });
            })->exists();
        abort_if($conflict, 422, 'This patient or department already has an appointment at that time.');

        $data['appointment_number'] = $numberService->next('appointment', 'APT');
        $data['booked_by'] = $request->user()->id;
        $data['status'] = 'scheduled';

        DB::transaction(function () use ($data, $request, &$appointment): void {
            $appointment = Appointment::create($data);
            AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'appointments', 'model_type' => Appointment::class, 'model_id' => $appointment->id, 'description' => 'Appointment scheduled.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
            $portalUserId = PatientPortalAccount::where('patient_id', $appointment->patient_id)->where('status', 'active')->value('user_id');
            if ($portalUserId) {
                Notification::create(['user_id' => $portalUserId, 'title' => 'Appointment scheduled', 'message' => 'Your appointment '.$appointment->appointment_number.' is scheduled for '.$appointment->scheduled_at->format('d M Y, H:i').'.', 'type' => 'appointment', 'metadata' => ['appointment_id' => $appointment->id]]);
            }
        });

        return redirect()->route('appointments.index')->with('success', 'Appointment scheduled successfully.');
    }

    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_if($request->user()->hasRole('doctor') && $appointment->doctor_id !== $request->user()->id, 403, 'Doctors can only manage their assigned appointments.');
        $data = $request->validate(['status' => ['required', 'in:scheduled,confirmed,checked_in,completed,cancelled,no_show'], 'status_reason' => ['required_if:status,cancelled,no_show', 'nullable', 'string', 'max:500']]);
        abort_if(in_array($appointment->status, ['completed', 'cancelled', 'no_show'], true) && $data['status'] !== $appointment->status, 422, 'This appointment is already closed.');
        DB::transaction(function () use ($appointment, $data, $request): void {
            $appointment->update(['status' => $data['status'], 'cancellation_reason' => in_array($data['status'], ['cancelled', 'no_show'], true) ? $data['status_reason'] : $appointment->cancellation_reason]);
            AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'update', 'module_name' => 'appointments', 'model_type' => Appointment::class, 'model_id' => $appointment->id, 'description' => 'Appointment status changed to '.$data['status'].'.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
            $portalUserId = PatientPortalAccount::where('patient_id', $appointment->patient_id)->where('status', 'active')->value('user_id');
            if ($portalUserId) {
                Notification::create([
                    'user_id' => $portalUserId,
                    'title' => 'Appointment status updated',
                    'message' => 'Appointment '.$appointment->appointment_number.' is now '.str_replace('_', ' ', $data['status']).'.',
                    'type' => 'appointment_status',
                    'metadata' => ['appointment_id' => $appointment->id, 'status' => $data['status']],
                ]);
            }
        });
        return back()->with('success', 'Appointment status updated.');
    }

    public function reschedule(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_if($request->user()->hasRole('doctor') && $appointment->doctor_id !== $request->user()->id, 403, 'Doctors can only manage their assigned appointments.');
        $data = $request->validate(['scheduled_at' => ['required', 'date', 'after:now']]);
        abort_if(in_array($appointment->status, ['completed', 'cancelled', 'no_show'], true), 422, 'Closed appointments cannot be rescheduled.');
        $conflict = Appointment::where('id', '!=', $appointment->id)
            ->where('scheduled_at', Carbon::parse($data['scheduled_at']))
            ->whereIn('status', ['scheduled', 'confirmed', 'checked_in'])
            ->where(function ($query) use ($appointment): void {
                $query->where('patient_id', $appointment->patient_id)
                    ->orWhere(function ($departmentQuery) use ($appointment): void {
                        $departmentQuery->where('department', $appointment->department)
                            ->where(function ($doctorQuery) use ($appointment): void {
                                if ($appointment->doctor_id) {
                                    $doctorQuery->where('doctor_id', $appointment->doctor_id);
                                } else {
                                    $doctorQuery->whereNull('doctor_id');
                                }
                            });
                    });
            })->exists();
        abort_if($conflict, 422, 'This patient or department already has an appointment at that time.');
        DB::transaction(function () use ($appointment, $data, $request): void {
            $appointment->update($data);
            AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'update', 'module_name' => 'appointments', 'model_type' => Appointment::class, 'model_id' => $appointment->id, 'description' => 'Appointment rescheduled.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        });
        return back()->with('success', 'Appointment rescheduled.');
    }

}
