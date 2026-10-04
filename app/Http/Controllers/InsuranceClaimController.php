<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\InsuranceClaim;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\DailyNumberService;

class InsuranceClaimController extends Controller
{
    public function index(): View
    {
        return view('insurance.index', ['claims' => InsuranceClaim::with('patient', 'invoice')->latest()->paginate(25), 'invoices' => Invoice::with('patient')->whereIn('status', ['pending', 'partially_paid'])->latest()->get()]);
    }

    public function store(Request $request, DailyNumberService $numberService): RedirectResponse
    {
        $data = $request->validate(['invoice_id' => ['required', 'exists:invoices,id'], 'patient_id' => ['required', 'exists:patients,id'], 'provider' => ['required', 'string'], 'amount' => ['required', 'numeric', 'min:0.01']]);
        $invoice = Invoice::withSum('payments', 'amount')->findOrFail($data['invoice_id']);
        abort_unless((int) $invoice->patient_id === (int) $data['patient_id'], 422, 'The claim patient must match the invoice patient.');
        $outstanding = (float) $invoice->total_amount - (float) ($invoice->payments_sum_amount ?? 0);
        abort_if((float) $data['amount'] > max(0, $outstanding), 422, 'The claim amount cannot exceed the invoice balance.');
        $data['claim_number'] = $numberService->next('insurance_claim', 'CLM');
        $data['status'] = 'submitted';
        $claim = InsuranceClaim::create($data);
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'insurance', 'model_type' => InsuranceClaim::class, 'model_id' => $claim->id, 'description' => 'Insurance claim submitted.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('success', 'Insurance claim submitted.');
    }

    public function review(Request $request, InsuranceClaim $claim): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected,submitted'], 'notes' => ['nullable', 'string']]);
        $claim->update(['status' => $data['status']]);
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'update', 'module_name' => 'insurance', 'model_type' => InsuranceClaim::class, 'model_id' => $claim->id, 'description' => 'Insurance claim reviewed as '.$data['status'].'.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('success', 'Insurance claim updated.');
    }
}