<?php

namespace App\Http\Controllers;

use App\Models\RadiologyRequest;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\Storage;
use App\Services\DailyNumberService;
use App\Services\DoctorPatientScope;

class RadiologyController extends Controller
{
    public function index(Request $request): View
    {
        return view('radiology.index', [
            'requests' => RadiologyRequest::with('patient', 'requestedBy', 'reportedBy')->when($request->user()->hasRole('doctor'), fn ($query) => $query->where('requested_by', $request->user()->id))->latest()->paginate(25),
            'patients' => app(DoctorPatientScope::class)->patientsFor($request->user())->orderBy('first_name')->get(),
        ]);
    }

    public function store(Request $request, DailyNumberService $numberService): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'modality' => ['required', 'in:X-Ray,Ultrasound,CT,MRI'],
        ]);
        $data['request_number'] = $numberService->next('radiology_request', 'RAD');
        abort_unless(app(DoctorPatientScope::class)->allows($request->user(), (int) $data['patient_id']), 403, 'You can only request imaging for patients assigned to you.');
        $data['requested_by'] = $request->user()->id;
        $data['status'] = 'pending';
        $radiologyRequest = RadiologyRequest::create($data);
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'radiology', 'model_type' => RadiologyRequest::class, 'model_id' => $radiologyRequest->id, 'description' => 'Radiology request created.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('success', 'Radiology request sent.');
    }

    public function report(Request $request, RadiologyRequest $radiologyRequest): RedirectResponse
    {
        abort_if($radiologyRequest->status === 'completed', 422, 'This radiology report is already completed.');
        $data = $request->validate(['report' => ['required', 'string', 'max:10000'], 'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,dcm', 'max:20480']]);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('radiology');
        }
        $radiologyRequest->update($data + ['status' => 'completed', 'reported_by' => $request->user()->id, 'reported_at' => now()]);
        if ($radiologyRequest->requested_by) {
            Notification::create(['user_id' => $radiologyRequest->requested_by, 'title' => 'Radiology report ready', 'message' => 'Report for '.$radiologyRequest->request_number.' is ready for review.', 'type' => 'radiology_result', 'metadata' => ['radiology_request_id' => $radiologyRequest->id]]);
        }
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'update', 'module_name' => 'radiology', 'model_type' => RadiologyRequest::class, 'model_id' => $radiologyRequest->id, 'description' => 'Radiology report entered.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('success', 'Radiology report saved.');
    }

    public function attachment(Request $request, RadiologyRequest $radiologyRequest): BinaryFileResponse
    {
        abort_unless($radiologyRequest->status === 'completed', 404);
        if ($request->user()->hasRole('doctor')) {
            abort_unless($radiologyRequest->requested_by === $request->user()->id, 403);
        }
        abort_unless($radiologyRequest->image_path && Storage::exists($radiologyRequest->image_path), 404);
        return response()->file(Storage::path($radiologyRequest->image_path));
    }
}
