<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\Patient;
use App\Models\Ward;
use App\Models\AuditLog;
use App\Models\NursingNote;
use App\Models\AdmissionVital;
use App\Models\MedicationAdministration;
use App\Models\AdmissionTransfer;
use App\Models\WardRound;
use App\Models\Prescription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Services\DailyNumberService;
use App\Services\DoctorPatientScope;

class AdmissionController extends Controller
{
    public function create(): View
    {
        $user = request()->user();
        return view('admissions.create', [
            'patients' => app(DoctorPatientScope::class)->patientsFor($user)->orderBy('first_name')->get(),
            'wards' => Ward::with(['beds'])->withCount(['admissions as occupied_beds' => fn ($query) => $query->where('status', 'admitted')])->orderBy('name')->get(),
        ]);
    }

    public function index(Request $request): View
    {
        $query = Admission::with(['patient', 'ward', 'nursingNotes.recordedBy', 'vitals', 'medicationAdministrations', 'transfers.fromWard', 'transfers.toWard', 'wardRounds.recordedBy'])->latest();
        if ($request->user()->hasRole('doctor')) {
            $query->whereIn('patient_id', app(DoctorPatientScope::class)->patientsFor($request->user())->select('patients.id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('admission_number', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($patientQuery) use ($search) {
                      $patientQuery->where('first_name', 'like', "%{$search}%")
                          ->orWhere('last_name', 'like', "%{$search}%")
                          ->orWhere('patient_number', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('ward_id')) {
            $query->where('ward_id', $request->input('ward_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $admissions = $query->paginate(25)->appends($request->query());

        $wards = Ward::with(['beds'])->withCount(['admissions as occupied_beds' => fn ($query) => $query->where('status', 'admitted')])->orderBy('name')->get();

        return view('admissions.index', [
            'admissions' => $admissions,
            'patients' => app(DoctorPatientScope::class)->patientsFor($request->user())->orderBy('first_name')->get(),
            'wards' => $wards,
            'summary' => [
                'active' => Admission::where('status', 'admitted')->count(),
                'today' => Admission::whereDate('admission_date', today())->count(),
                'available_beds' => max(0, (int) $wards->sum('capacity') - (int) Admission::where('status', 'admitted')->count()),
                'notes_today' => NursingNote::whereDate('recorded_at', today())->count(),
            ],
        ]);
    }

    public function store(Request $request, DailyNumberService $numberService): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'ward_id' => ['required', 'exists:wards,id'],
            'bed_id' => ['nullable', 'exists:beds,id'],
            'notes' => ['nullable', 'string'],
        ]);
        abort_unless(! $request->user()->hasRole('doctor') || app(DoctorPatientScope::class)->allows($request->user(), (int) $data['patient_id']), 403, 'You can only admit patients assigned to you.');
        $admission = DB::transaction(function () use ($data, $request, $numberService): Admission {
            Patient::whereKey($data['patient_id'])->lockForUpdate()->firstOrFail();
            abort_if(Admission::where('patient_id', $data['patient_id'])->where('status', 'admitted')->exists(), 422, 'This patient already has an active admission.');
            if (! empty($data['ward_id'])) {
                $ward = Ward::whereKey($data['ward_id'])->lockForUpdate()->firstOrFail();
                abort_unless(in_array($ward->status, ['active', 'available'], true), 422, 'This ward is not available.');
                abort_if($ward->admissions()->where('status', 'admitted')->count() >= $ward->capacity, 422, 'This ward has no available bed.');
                $existingBeds = $ward->beds()->count();
                for ($number = $existingBeds + 1; $number <= $ward->capacity; $number++) {
                    $ward->beds()->create(['bed_number' => 'BED-'.$number, 'status' => 'available']);
                }
                $bed = ! empty($data['bed_id']) ? $ward->beds()->whereKey($data['bed_id'])->lockForUpdate()->firstOrFail() : $ward->beds()->where('status', 'available')->whereDoesntHave('activeAdmission')->lockForUpdate()->first();
                abort_if(! $bed, 422, 'This ward has no available bed.');
                $data['bed_id'] = $bed->id;
            }
            $data['admission_number'] = $numberService->next('admission', 'ADM');
            $data['admission_date'] = now();
            $data['status'] = 'admitted';
            return Admission::create($data);
        });
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'admissions', 'model_type' => Admission::class, 'model_id' => $admission->id, 'description' => 'Patient admitted.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('success', 'Patient admitted successfully.');
    }

    public function discharge(Request $request, Admission $admission): RedirectResponse
    {
        abort_unless(! $request->user()->hasRole('doctor') || app(DoctorPatientScope::class)->allows($request->user(), (int) $admission->patient_id), 403, 'You can only manage admissions for patients assigned to you.');
        $data = $request->validate(['discharge_summary' => ['required', 'string', 'max:10000'], 'discharge_condition' => ['required', 'string', 'max:1000'], 'discharge_medications' => ['nullable', 'string', 'max:5000'], 'follow_up_date' => ['nullable', 'date']]);
        DB::transaction(function () use ($admission, $request, $data): void {
            abort_unless($admission->status === 'admitted', 422, 'Only admitted patients can be discharged.');
            $admission->update(['status' => 'discharged'] + $data + ['discharged_by' => $request->user()->id, 'discharged_at' => now()]);
            AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'update', 'module_name' => 'admissions', 'model_type' => Admission::class, 'model_id' => $admission->id, 'description' => 'Patient discharged.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        });
        return back()->with('success', 'Patient discharged successfully.');
    }

    public function admissionVital(Request $request, Admission $admission): RedirectResponse
    {
        abort_unless($admission->status === 'admitted', 422, 'Vitals can only be recorded for admitted patients.');
        $data = $request->validate(['temperature' => ['nullable', 'numeric', 'between:25,45'], 'blood_pressure' => ['nullable', 'string', 'max:20'], 'pulse' => ['nullable', 'integer', 'between:20,250'], 'respiratory_rate' => ['nullable', 'integer', 'between:5,80'], 'oxygen_saturation' => ['nullable', 'numeric', 'between:0,100'], 'weight' => ['nullable', 'numeric', 'between:0,500'], 'consciousness' => ['nullable', 'in:alert,voice,pain,unresponsive']]);
        AdmissionVital::create($data + ['admission_id' => $admission->id, 'recorded_by' => $request->user()->id, 'recorded_at' => now()]);
        return back()->with('success', 'IPD vital signs recorded successfully.');
    }

    public function medicationAdministration(Request $request, Admission $admission): RedirectResponse
    {
        abort_unless($admission->status === 'admitted', 422, 'Medication can only be administered to admitted patients.');
        $data = $request->validate(['prescription_id' => ['nullable', 'exists:prescriptions,id'], 'medication_name' => ['required', 'string', 'max:150'], 'dose' => ['required', 'string', 'max:100'], 'route' => ['required', 'in:oral,iv,im,sc,topical,other'], 'scheduled_at' => ['nullable', 'date'], 'status' => ['required', 'in:given,refused,missed'], 'notes' => ['nullable', 'string', 'max:1000']]);
        if (! empty($data['prescription_id'])) {
            abort_unless(Prescription::whereKey($data['prescription_id'])->where('patient_id', $admission->patient_id)->exists(), 422, 'Prescription does not belong to this patient.');
        }
        MedicationAdministration::create($data + ['admission_id' => $admission->id, 'administered_by' => $request->user()->id, 'administered_at' => $data['status'] === 'given' ? now() : null]);
        return back()->with('success', 'Medication chart updated successfully.');
    }

    public function transfer(Request $request, Admission $admission): RedirectResponse
    {
        abort_unless($admission->status === 'admitted', 422, 'Only admitted patients can be transferred.');
        $data = $request->validate(['to_ward_id' => ['required', 'exists:wards,id'], 'reason' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($data, $admission, $request): void {
            $lockedAdmission = Admission::lockForUpdate()->findOrFail($admission->id);
            $ward = Ward::whereKey($data['to_ward_id'])->lockForUpdate()->firstOrFail();
            abort_if($lockedAdmission->ward_id === $ward->id, 422, 'Patient is already in this ward.');
            abort_unless(in_array($ward->status, ['active', 'available'], true), 422, 'Destination ward is not available.');
            abort_if($ward->admissions()->where('status', 'admitted')->count() >= $ward->capacity, 422, 'Destination ward has no available bed.');
            $fromWardId = $lockedAdmission->ward_id;
            $lockedAdmission->update(['ward_id' => $ward->id]);
            AdmissionTransfer::create(['admission_id' => $lockedAdmission->id, 'from_ward_id' => $fromWardId, 'to_ward_id' => $ward->id, 'reason' => $data['reason'], 'transferred_by' => $request->user()->id, 'transferred_at' => now()]);
        });
        return back()->with('success', 'Patient transferred successfully.');
    }

    public function wardRound(Request $request, Admission $admission): RedirectResponse
    {
        abort_unless(! $request->user()->hasRole('doctor') || app(DoctorPatientScope::class)->allows($request->user(), (int) $admission->patient_id), 403, 'You can only record rounds for patients assigned to you.');
        abort_unless($admission->status === 'admitted', 422, 'Ward rounds can only be recorded for admitted patients.');
        $data = $request->validate(['note' => ['required', 'string', 'max:10000'], 'plan' => ['nullable', 'string', 'max:5000']]);
        WardRound::create($data + ['admission_id' => $admission->id, 'recorded_by' => $request->user()->id, 'round_date' => today(), 'recorded_at' => now()]);
        return back()->with('success', 'Ward round recorded successfully.');
    }

    public function nurseVitalsIndex(): View
    {
        $admissions = Admission::with(['patient', 'ward', 'vitals.recordedBy'])
            ->where('status', 'admitted')
            ->latest('admission_date')
            ->get();

        $recentVitals = AdmissionVital::with(['admission.patient', 'recordedBy'])
            ->latest('recorded_at')
            ->take(12)
            ->get();

        return view('nurse.vitals', [
            'admissions' => $admissions,
            'recentVitals' => $recentVitals,
        ]);
    }

    public function storeNurseVitals(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'admission_id' => ['required', 'exists:admissions,id'],
            'temperature' => ['nullable', 'numeric', 'between:25,45'],
            'blood_pressure' => ['nullable', 'string', 'max:20'],
            'pulse' => ['nullable', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'oxygen_saturation' => ['nullable', 'numeric', 'between:0,100'],
            'weight' => ['nullable', 'numeric', 'between:0,500'],
            'consciousness' => ['nullable', 'in:alert,voice,pain,unresponsive'],
        ]);

        $admission = Admission::findOrFail($data['admission_id']);
        abort_unless($admission->status === 'admitted', 422, 'Vitals can only be recorded for admitted patients.');

        AdmissionVital::create($data + ['recorded_by' => $request->user()->id, 'recorded_at' => now()]);
        return back()->with('success', 'Vital signs saved successfully.');
    }

    public function nursingCareIndex(): View
    {
        $admissions = Admission::with(['patient', 'ward', 'nursingNotes.recordedBy'])
            ->where('status', 'admitted')
            ->latest('admission_date')
            ->get();

        $recentNotes = NursingNote::with(['admission.patient', 'recordedBy'])
            ->latest('recorded_at')
            ->take(12)
            ->get();

        return view('nursing-care.index', [
            'admissions' => $admissions,
            'recentNotes' => $recentNotes,
        ]);
    }

    public function storeNursingCareNote(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'admission_id' => ['required', 'exists:admissions,id'],
            'note_type' => ['required', 'in:progress,observation,care,medication,handover'],
            'note' => ['required', 'string', 'max:5000'],
        ]);

        $admission = Admission::findOrFail($data['admission_id']);
        abort_unless($admission->status === 'admitted', 422, 'Nursing notes can only be added for admitted patients.');

        $note = NursingNote::create($data + ['recorded_by' => $request->user()->id, 'recorded_at' => now()]);
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'nursing_notes', 'model_type' => NursingNote::class, 'model_id' => $note->id, 'description' => 'Nursing note recorded.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return back()->with('success', 'Nursing note recorded successfully.');
    }

    public function nursingNote(Request $request, Admission $admission): RedirectResponse
    {
        abort_unless($admission->status === 'admitted', 422, 'Nursing notes can only be added for admitted patients.');
        $data = $request->validate(['note_type' => ['required', 'in:progress,observation,care,medication,handover'], 'note' => ['required', 'string', 'max:5000']]);
        $note = NursingNote::create($data + ['admission_id' => $admission->id, 'recorded_by' => $request->user()->id, 'recorded_at' => now()]);
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'nursing_notes', 'model_type' => NursingNote::class, 'model_id' => $note->id, 'description' => 'Nursing note recorded.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('success', 'Nursing note recorded successfully.');
    }
}
