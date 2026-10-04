<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\DailyNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\DoctorPatientScope;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $patients = app(DoctorPatientScope::class)->patientsFor($request->user())->when($search !== '', function ($query) use ($search): void {
            $query->where(function ($patientQuery) use ($search): void {
                $fullName = $patientQuery->getConnection()->getDriverName() === 'sqlite'
                    ? "first_name || ' ' || last_name"
                    : "CONCAT(first_name, ' ', last_name)";
                $patientQuery->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhereRaw("TRIM({$fullName}) LIKE ?", ['%'.$search.'%'])
                    ->orWhere('patient_number', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        })->latest()->paginate(25)->withQueryString();
        return view('patients.index', [
            'patients' => $patients,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('patients.create');
    }

    public function timeline(Patient $patient): View
    {
        abort_unless(app(DoctorPatientScope::class)->allows(request()->user(), $patient->id), 403, 'You can only view patients assigned to you.');
        $patient->load([
            'appointments' => fn ($query) => $query->latest('scheduled_at'),
            'visits' => fn ($query) => $query->latest('visit_date'),
            'labRequests' => fn ($query) => $query->latest('created_at'),
            'prescriptions' => fn ($query) => $query->latest('created_at'),
            'invoices' => fn ($query) => $query->latest('created_at'),
        ]);

        $timeline = collect();

        foreach ($patient->appointments as $appointment) {
            $timeline->push([
                'time' => $appointment->scheduled_at ?? $appointment->created_at,
                'title' => 'Appointment',
                'subtitle' => $appointment->department ?? 'General scheduling',
                'content' => $appointment->status ? ucfirst(str_replace('_', ' ', $appointment->status)) : 'Scheduled',
                'meta' => $appointment->appointment_number,
            ]);
        }

        foreach ($patient->visits as $visit) {
            $timeline->push([
                'time' => $visit->visit_date ?? $visit->created_at,
                'title' => 'Consultation',
                'subtitle' => $visit->department ?? 'Clinical review',
                'content' => $visit->diagnosis ?: ($visit->complaint ?? 'Clinical consultation'),
                'meta' => $visit->visit_number,
            ]);
        }

        foreach ($patient->labRequests as $labRequest) {
            $timeline->push([
                'time' => $labRequest->completed_at ?? $labRequest->created_at,
                'title' => 'Laboratory',
                'subtitle' => $labRequest->test_name,
                'content' => $labRequest->result ?: ($labRequest->status ?? 'Pending'),
                'meta' => $labRequest->request_number,
            ]);
        }

        foreach ($patient->prescriptions as $prescription) {
            $timeline->push([
                'time' => $prescription->created_at,
                'title' => 'Prescription',
                'subtitle' => 'Medication plan',
                'content' => $prescription->notes ?: 'Medication issued',
                'meta' => $prescription->prescription_number ?? 'Rx',
            ]);
        }

        foreach ($patient->invoices as $invoice) {
            $timeline->push([
                'time' => $invoice->created_at,
                'title' => 'Billing',
                'subtitle' => $invoice->service_description ?: 'Invoice',
                'content' => $invoice->status ? ucfirst($invoice->status) : 'Generated',
                'meta' => $invoice->invoice_number,
            ]);
        }

        $timeline = $timeline->sortByDesc(fn ($entry) => $entry['time'])->values();

        return view('patients.timeline', [
            'patient' => $patient,
            'timeline' => $timeline,
        ]);
    }

    public function store(Request $request, DailyNumberService $numberService)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+()\s-]{7,25}$/'],
            'address' => ['nullable', 'string'],
            'next_of_kin_name' => ['nullable', 'string'],
            'next_of_kin_phone' => ['nullable', 'string'],
            'insurance_provider' => ['nullable', 'string'],
        ]);

        $data['phone'] = $this->normalizePhone($data['phone'] ?? null);
        $data['next_of_kin_phone'] = $this->normalizePhone($data['next_of_kin_phone'] ?? null);
        if (! empty($data['phone']) && Patient::where('phone', $data['phone'])->exists()) {
            abort(422, 'A patient with this phone number already exists.');
        }

        $data['patient_number'] = $numberService->next('patient', 'PT');
        DB::transaction(function () use ($data, $request, &$patient): void {
            $patient = Patient::create($data);
            AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'patients', 'model_type' => Patient::class, 'model_id' => $patient->id, 'description' => 'Patient registered.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        });

        return redirect()->route('patients.index')->with('success', 'Patient registered successfully.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403, 'Only super administrators can purge patient records.');

        $deletedCount = $this->purgePatients();

        return redirect()->route('patients.index')->with('success', 'All patient records were permanently removed.');
    }

    public function purgePatients(): int
    {
        $deletedCount = DB::table('patients')->count();

        if ($deletedCount === 0) {
            return 0;
        }

        DB::transaction(function (): void {
            foreach ($this->patientPurgeTables() as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                if (Schema::hasColumn($table, 'patient_id')) {
                    DB::table($table)->whereNotNull('patient_id')->delete();
                    continue;
                }

                if ($table === 'patients') {
                    DB::table('patients')->delete();
                }
            }
        });

        return $deletedCount;
    }

    private function patientPurgeTables(): array
    {
        return [
            'patient_portal_accounts',
            'appointments',
            'visits',
            'lab_requests',
            'radiology_requests',
            'admissions',
            'invoices',
            'insurance_claims',
            'prescriptions',
            'emergency_cases',
            'death_records',
            'patients',
        ];
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }
        return preg_replace('/[\s()\-]/', '', trim($phone));
    }
}
