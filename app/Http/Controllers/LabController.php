<?php

namespace App\Http\Controllers;

use App\Models\LabRequest;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\DailyNumberService;
use Illuminate\Support\Facades\DB;
use App\Services\DoctorPatientScope;

class LabController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = LabRequest::with('patient')->latest();
        if ($user->hasAnyRole(['doctor', 'nurse']) && ! $user->hasAnyRole(['super-admin', 'hospital-manager', 'lab-technician'])) {
            $query->where('requested_by', $user->id);
        }

        return view('lab.index', [
            'requests' => $query->paginate(25)->withQueryString(),
            'patients' => app(DoctorPatientScope::class)->patientsFor($user)->where('is_active', true)->orderBy('first_name')->get(),
        ]);
    }

    public function store(Request $request, DailyNumberService $numberService): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'test_name' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $patient = Patient::findOrFail($data['patient_id']);
        abort_unless(app(DoctorPatientScope::class)->allows($request->user(), (int) $data['patient_id']), 403, 'You can only request tests for patients assigned to you.');
        abort_if(! $patient->is_active, 422, 'Laboratory requests cannot be created for an inactive patient.');
        $data['request_number'] = $numberService->next('lab_request', 'LAB');
        $data['requested_by'] = $request->user()->id;
        $data['status'] = 'pending';
        $configuredPrice = collect(config('billing.prices.lab', []))->first(fn ($price, $testName) => strcasecmp(trim($testName), trim($data['test_name'])) === 0);
        $data['billing_amount'] = $configuredPrice ?? config('billing.prices.lab_default');
        DB::transaction(function () use ($data, $request, &$labRequest): void {
            $labRequest = LabRequest::create($data);
            AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'lab', 'model_type' => LabRequest::class, 'model_id' => $labRequest->id, 'description' => 'Laboratory test requested.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        });
        return back()->with('success', 'Laboratory request sent.');
    }

    public function complete(Request $request, LabRequest $labRequest): RedirectResponse
    {
        $data = $request->validate(['result' => ['required', 'string', 'max:5000'], 'reference_range' => ['nullable', 'string', 'max:255']]);
        DB::transaction(function () use ($labRequest, $data, $request): void {
            $lockedRequest = LabRequest::whereKey($labRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedRequest->status, ['pending', 'collected', 'processing'], true), 422, 'Only active laboratory samples can be completed.');
            $lockedRequest->update($data + ['status' => 'completed', 'completed_by' => $request->user()->id, 'completed_at' => now()]);
            $lockedRequest->resultHistories()->create($data + ['action' => 'completed', 'changed_by' => $request->user()->id]);
            if ($lockedRequest->requested_by) {
                Notification::create(['user_id' => $lockedRequest->requested_by, 'title' => 'Lab result ready', 'message' => 'Result for '.$lockedRequest->request_number.' is ready for review.', 'type' => 'lab_result', 'metadata' => ['lab_request_id' => $lockedRequest->id]]);
            }
            AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'update', 'module_name' => 'lab', 'model_type' => LabRequest::class, 'model_id' => $lockedRequest->id, 'description' => 'Laboratory result entered.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        });
        return back()->with('success', 'Laboratory result saved.');
    }

    public function updateSampleStatus(Request $request, LabRequest $labRequest): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:collected,processing,rejected'], 'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($labRequest, $data, $request): void {
            $lockedRequest = LabRequest::whereKey($labRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedRequest->status, ['pending', 'collected', 'processing'], true), 422, 'This sample cannot change status now.');
            $updates = ['status' => $data['status']];
            if ($data['status'] === 'collected') {
                $updates += ['collected_by' => $request->user()->id, 'collected_at' => now()];
            } elseif ($data['status'] === 'processing') {
                abort_unless(in_array($lockedRequest->status, ['collected', 'processing'], true), 422, 'Collect the sample before processing it.');
                $updates += ['processing_started_by' => $request->user()->id, 'processing_started_at' => now()];
            } else {
                $updates += ['rejected_by' => $request->user()->id, 'rejected_at' => now(), 'rejection_reason' => $data['rejection_reason']];
            }
            $lockedRequest->update($updates);
            AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'update', 'module_name' => 'lab', 'model_type' => LabRequest::class, 'model_id' => $lockedRequest->id, 'description' => 'Laboratory sample status changed to '.$data['status'].'.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        });
        return back()->with('success', 'Laboratory sample status updated.');
    }

    public function amend(Request $request, LabRequest $labRequest): RedirectResponse
    {
        $data = $request->validate(['result' => ['required', 'string', 'max:5000'], 'reference_range' => ['nullable', 'string', 'max:255'], 'reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($labRequest, $data, $request): void {
            $lockedRequest = LabRequest::whereKey($labRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedRequest->status === 'completed', 422, 'Only completed laboratory requests can be amended.');
            $lockedRequest->update(['result' => $data['result'], 'reference_range' => $data['reference_range'] ?? null, 'completed_by' => $request->user()->id, 'completed_at' => now()]);
            $lockedRequest->resultHistories()->create($data + ['action' => 'amended', 'changed_by' => $request->user()->id]);
            AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'update', 'module_name' => 'lab', 'model_type' => LabRequest::class, 'model_id' => $lockedRequest->id, 'description' => 'Laboratory result amended: '.$data['reason'], 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        });
        return back()->with('success', 'Laboratory result amended and recorded in history.');
    }

    public function amendForm(LabRequest $labRequest): View
    {
        abort_unless($labRequest->status === 'completed', 404);

        return view('lab.amend', ['labRequest' => $labRequest->load('resultHistories.changedBy')]);
    }

    public function history(LabRequest $labRequest): View
    {
        return view('lab.history', ['labRequest' => $labRequest->load('patient', 'resultHistories.changedBy')]);
    }
}
