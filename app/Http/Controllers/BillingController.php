<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\AuditLog;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\JourneyBillingService;
use Illuminate\View\View;
use App\Services\DailyNumberService;
use Illuminate\Http\JsonResponse;

class BillingController extends Controller
{
    public function index(): View
    {
        return view('billing.index', [
            'invoices' => Invoice::with('items')->latest()->paginate(25),
            'patients' => Patient::orderBy('first_name')->get(),
        ]);
    }

    public function patientSuggestions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $patients = Patient::where('is_active', true)->where(function ($query) use ($search): void {
            $fullName = $query->getConnection()->getDriverName() === 'sqlite'
                ? "first_name || ' ' || last_name"
                : "CONCAT(first_name, ' ', last_name)";
            $query->where('first_name', 'like', '%'.$search.'%')
                ->orWhere('last_name', 'like', '%'.$search.'%')
                ->orWhereRaw("TRIM({$fullName}) LIKE ?", ['%'.$search.'%'])
                ->orWhere('patient_number', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%');
        })->orderBy('first_name')->limit(10)->get(['id', 'first_name', 'last_name', 'patient_number']);

        return response()->json($patients->map(fn (Patient $patient): array => [
            'id' => $patient->id,
            'name' => trim($patient->first_name.' '.$patient->last_name),
            'label' => trim($patient->first_name.' '.$patient->last_name).' - '.$patient->patient_number,
        ])->values());
    }

    public function patientServices(Request $request, JourneyBillingService $billingService): JsonResponse
    {
        $data = $request->validate(['patient_id' => ['required', 'exists:patients,id']]);
        $items = $billingService->calculate(Patient::findOrFail($data['patient_id']));

        return response()->json([
            'description' => $items->pluck('description')->implode("\n"),
            'total' => $items->sum('total_price'),
            'items' => $items->map(fn (array $item): array => [
                'description' => $item['description'],
                'total_price' => $item['total_price'],
            ])->values(),
        ]);
    }

    public function collections(): View
    {
        $invoices = Invoice::with('patient', 'payments')
            ->withSum('payments', 'amount')
            ->whereIn('status', ['pending', 'partially_paid'])
            ->orderByRaw('total_amount - COALESCE((SELECT SUM(amount) FROM payments WHERE payments.invoice_id = invoices.id), 0) DESC')
            ->get()
            ->map(function (Invoice $invoice) {
                $invoice->outstanding_balance = (float) $invoice->total_amount - (float) ($invoice->payments_sum_amount ?? 0);
                return $invoice;
            });

        return view('billing.collections', [
            'invoices' => $invoices,
            'totalOutstanding' => $invoices->sum('outstanding_balance'),
        ]);
    }

    public function store(Request $request, DailyNumberService $numberService): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'service_description' => ['required', 'string'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
        ]);
        $data['invoice_number'] = $numberService->next('invoice', 'INV');
        $data['status'] = 'pending';
        $invoice = Invoice::create($data);

        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'billing', 'model_type' => Invoice::class, 'model_id' => $invoice->id, 'description' => 'Invoice created.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return back()->with('success', 'Invoice created successfully.');
    }

    public function generateJourneyInvoice(Request $request, JourneyBillingService $billingService): RedirectResponse
    {
        $data = $request->validate(['patient_id' => ['required', 'exists:patients,id']]);
        $patient = Patient::findOrFail($data['patient_id']);

        if ($billingService->calculate($patient)->isEmpty()) {
            return back()->with('error', 'Hakuna huduma mpya iliyokamilika ya kutoza kwa mgonjwa huyu. Huduma zilizokwishawekwa kwenye invoice hazitarudiwa mara mbili.');
        }

        $invoice = $billingService->createInvoice($patient, $request->user()->id);

        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'billing', 'model_type' => Invoice::class, 'model_id' => $invoice->id, 'description' => 'Automatic journey invoice generated.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return redirect()->route('billing.index')->with('success', 'Journey invoice generated with '.$invoice->items()->count().' billable item(s).');
    }
}
