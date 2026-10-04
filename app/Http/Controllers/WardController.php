<?php

namespace App\Http\Controllers;

use App\Models\Ward;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Services\DoctorPatientScope;

class WardController extends Controller
{
    public function index(): View
    {
        $query = Ward::withCount(['admissions as occupied_beds' => fn ($query) => $query->where('status', 'admitted')])->withCount(['beds as total_beds'])->with('beds.activeAdmission.patient');
        if (request()->user()->hasRole('doctor')) {
            $patientIds = app(DoctorPatientScope::class)->patientsFor(request()->user())->select('patients.id');
            $query->with(['beds.activeAdmission' => fn ($admissionQuery) => $admissionQuery->whereIn('patient_id', $patientIds)]);
        }
        $wards = $query->latest()->paginate(25);

        return view('wards.index', [
            'wards' => $wards,
            'zoneSummary' => [
                'ICU' => Ward::where('zone', 'ICU')->count(),
                'Labour & Maternity' => Ward::where('zone', 'Labour & Maternity')->count(),
                'Paediatrics' => Ward::where('zone', 'Paediatrics')->count(),
                'Male' => Ward::where('gender_group', 'male')->count(),
                'Female' => Ward::whereIn('gender_group', ['female', 'women'])->count(),
                'Children' => Ward::where('gender_group', 'children')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'zone' => ['nullable', 'string', 'max:80', 'in:General,ICU,Labour & Maternity,Paediatrics,Emergency,High Dependency'],
            'ward_type' => ['nullable', 'string', 'max:80', 'in:general,critical-care,maternity,paediatric,observation,postnatal,medical,surgical'],
            'gender_group' => ['nullable', 'string', 'max:20', 'in:male,female,women,children,mixed'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,inactive'],
        ]);
        $data['zone'] ??= 'General';
        $data['ward_type'] ??= 'general';
        $data['gender_group'] ??= 'mixed';
        $ward = DB::transaction(function () use ($data): Ward {
            $ward = Ward::create($data);
            for ($number = 1; $number <= $ward->capacity; $number++) {
                $ward->beds()->create(['bed_number' => 'BED-'.$number, 'status' => 'available']);
            }
            return $ward;
        });
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'wards', 'model_type' => Ward::class, 'model_id' => $ward->id, 'description' => 'Ward created with beds and zone metadata.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('success', 'Ward created successfully.');
    }

    public function update(Request $request, Ward $ward): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'zone' => ['required', 'string', 'max:80', 'in:General,ICU,Labour & Maternity,Paediatrics,Emergency,High Dependency'],
            'ward_type' => ['required', 'string', 'max:80', 'in:general,critical-care,maternity,paediatric,observation,postnatal,medical,surgical'],
            'gender_group' => ['required', 'string', 'max:20', 'in:male,female,women,children,mixed'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:active,inactive'],
        ]);
        abort_if($data['capacity'] < $ward->admissions()->where('status', 'admitted')->count(), 422, 'Capacity cannot be below occupied beds.');
        $ward->update($data);
        $currentBeds = $ward->beds()->count();
        for ($number = $currentBeds + 1; $number <= $ward->capacity; $number++) {
            $ward->beds()->create(['bed_number' => 'BED-'.$number, 'status' => 'available']);
        }
        return back()->with('success', 'Ward updated successfully.');
    }
}
