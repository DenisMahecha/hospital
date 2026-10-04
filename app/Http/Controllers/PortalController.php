<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PatientPortalAccount;
use App\Models\Patient;
use App\Models\LabRequest;
use App\Models\LabResultRead;
use App\Models\RadiologyRequest;
use App\Models\RadiologyResultRead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function index(): View
    {
        $query = PatientPortalAccount::with('patient')->latest();

        if (auth()->user()->hasRole('patient')) {
            $query->where('user_id', auth()->id());
        }

        $account = auth()->user()->hasRole('patient') ? $query->first() : null;
        $patient = $account?->patient;
        $upcomingAppointment = $patient?->appointments()->whereIn('status', ['scheduled', 'confirmed', 'checked_in'])->orderBy('scheduled_at')->first();
        $outstandingBalance = $patient?->invoices()->withSum('payments', 'amount')->whereIn('status', ['pending', 'partially_paid'])->get()->sum(fn ($invoice) => max(0, (float) $invoice->total_amount - (float) ($invoice->payments_sum_amount ?? 0))) ?? 0;
        $radiologyRequests = $patient?->radiologyRequests()->where('status', 'completed')->latest()->take(10)->get() ?? collect();

        return view('portal.index', ['patients' => $query->take(20)->get(), 'allPatients' => auth()->user()->hasRole('super-admin') ? Patient::with('portalAccounts')->orderBy('first_name')->get() : collect(), 'patient' => $patient, 'appointments' => $patient?->appointments()->latest()->take(10)->get() ?? collect(), 'upcomingAppointment' => $upcomingAppointment, 'outstandingBalance' => $outstandingBalance, 'prescriptions' => $patient?->prescriptions()->latest()->take(10)->get() ?? collect(), 'invoices' => $patient?->invoices()->latest()->take(10)->get() ?? collect(), 'labRequests' => $patient?->labRequests()->latest()->take(10)->get() ?? collect(), 'radiologyRequests' => $radiologyRequests]);
    }

    public function toggleLink(Request $request, PatientPortalAccount $portalAccount): RedirectResponse
    {
        $portalAccount->update(['status' => $portalAccount->status === 'active' ? 'disabled' : 'active']);
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'update', 'module_name' => 'patient-portal', 'model_type' => PatientPortalAccount::class, 'model_id' => $portalAccount->id, 'description' => 'Patient portal account '.($portalAccount->status === 'active' ? 'linked.' : 'unlinked.'), 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return back()->with('success', $portalAccount->status === 'active' ? 'Patient portal linked.' : 'Patient portal unlinked.');
    }

    public function labResult(Request $request, LabRequest $labRequest): View
    {
        abort_unless($labRequest->status === 'completed', 404);
        $account = PatientPortalAccount::where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();
        abort_unless($labRequest->patient_id === $account->patient_id, 403);
        LabResultRead::updateOrCreate(['lab_request_id' => $labRequest->id, 'user_id' => $request->user()->id], ['read_at' => now()]);

        return view('portal.lab-result', ['labRequest' => $labRequest->load('patient', 'resultHistories')]);
    }

    public function radiologyResult(Request $request, RadiologyRequest $radiologyRequest): View
    {
        abort_unless($radiologyRequest->status === 'completed', 404);
        $account = PatientPortalAccount::where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();
        abort_unless($radiologyRequest->patient_id === $account->patient_id, 403);
        RadiologyResultRead::updateOrCreate(['radiology_request_id' => $radiologyRequest->id, 'user_id' => $request->user()->id], ['read_at' => now()]);

        return view('portal.radiology-result', ['radiologyRequest' => $radiologyRequest->load('patient', 'requestedBy', 'reportedBy')]);
    }
}
