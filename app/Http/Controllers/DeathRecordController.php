<?php

namespace App\Http\Controllers;

use App\Models\DeathRecord;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Services\DoctorPatientScope;

class DeathRecordController extends Controller
{
    public function index(Request $request): View
    {
        return view('deaths.index', [
            'records' => DeathRecord::with('patient')->when($request->user()->hasRole('doctor'), fn ($query) => $query->whereIn('patient_id', app(DoctorPatientScope::class)->patientsFor($request->user())->select('patients.id')))->latest('date_of_death')->paginate(25),
            'patients' => app(DoctorPatientScope::class)->patientsFor($request->user())->orderBy('first_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'date_of_death' => ['required', 'date'],
            'time_of_death' => ['nullable', 'date_format:H:i'],
            'cause' => ['required', 'string', 'max:2000'],
            'location' => ['required', 'string', 'max:150'],
            'doctor_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        abort_unless(! $request->user()->hasRole('doctor') || app(DoctorPatientScope::class)->allows($request->user(), (int) $data['patient_id']), 403, 'You can only record deaths for patients assigned to you.');

        DB::transaction(function () use ($data): void {
            DeathRecord::create($data);
        });

        return back()->with('success', 'Death record saved successfully.');
    }
}
