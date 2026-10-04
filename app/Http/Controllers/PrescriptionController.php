<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\DailyNumberService;
use App\Services\DoctorPatientScope;
use App\Models\Drug;
use Illuminate\Http\JsonResponse;

class PrescriptionController extends Controller
{
    public function index(Request $request): View
    {
        $scope = app(DoctorPatientScope::class);
        $patients = $scope->patientsFor($request->user())->orderBy('first_name')->limit(500)->get();
        $prescriptions = Prescription::with('patient')->when($request->user()->hasRole('doctor'), fn ($query) => $query->where('prescribed_by', $request->user()->id))->latest()->paginate(25);
        return view('prescriptions.index', compact('prescriptions', 'patients'));
    }

    public function drugSuggestions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $drugs = Drug::where('status', 'active')
            ->where(function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('category', 'like', '%'.$search.'%');
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['name', 'category', 'form']);

        return response()->json($drugs->map(fn (Drug $drug): array => [
            'name' => $drug->name,
            'label' => $drug->name.' - '.$drug->form.' ('.$drug->category.')',
        ])->values());
    }

    public function store(Request $request, DailyNumberService $numberService): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'medication_name' => ['required', 'string'],
            'dosage' => ['nullable', 'string'],
            'frequency' => ['nullable', 'string'],
            'duration' => ['nullable', 'string'],
            'quantity_prescribed' => ['nullable', 'integer', 'min:1'],
        ]);
        $data['prescription_number'] = $numberService->next('prescription', 'RX');
        abort_unless(app(DoctorPatientScope::class)->allows($request->user(), (int) $data['patient_id']), 403, 'You can only prescribe for patients assigned to you.');
        $data['prescribed_by'] = $request->user()->id;
        $data['status'] = 'active';
        $prescription = Prescription::create($data);

        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'prescriptions', 'model_type' => Prescription::class, 'model_id' => $prescription->id, 'description' => 'Prescription recorded.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return redirect()->route('prescriptions.index')->with('success', 'Prescription recorded successfully.');
    }
}
