<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CashierReconciliation;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReconciliationController extends Controller
{
    public function systemTotal(Request $request): JsonResponse
    {
        $data = $request->validate(['business_date' => ['required', 'date']]);
        $total = Payment::where('received_by', $request->user()->id)
            ->whereDate('paid_at', $data['business_date'])
            ->where('status', 'completed')
            ->sum('amount');

        return response()->json(['total' => (float) $total]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['business_date' => ['required', 'date'], 'declared_total' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string']]);
        $systemTotal = Payment::where('received_by', $request->user()->id)->whereDate('paid_at', $data['business_date'])->where('status', 'completed')->sum('amount');
        $reconciliation = CashierReconciliation::updateOrCreate(['cashier_id' => $request->user()->id, 'business_date' => $data['business_date']], ['system_total' => $systemTotal, 'declared_total' => $data['declared_total'], 'variance' => $data['declared_total'] - $systemTotal, 'notes' => $data['notes'] ?? null]);
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'finance', 'model_type' => CashierReconciliation::class, 'model_id' => $reconciliation->id, 'description' => 'Cashier reconciliation completed.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return back()->with('success', 'Cashier reconciliation saved.');
    }
}