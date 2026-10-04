<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $businessDate = $request->query('business_date', today()->format('Y-m-d'));
        $systemTotal = Payment::where('received_by', $request->user()->id)->whereDate('paid_at', $businessDate)->where('status', 'completed')->sum('amount');
        return view('payments.index', [
            'invoices' => Invoice::with('patient')->withSum('payments', 'amount')->latest()->paginate(25),
            'payments' => Payment::with('invoice.patient')->latest('paid_at')->take(20)->get(),
            'businessDate' => $businessDate,
            'systemTotal' => $systemTotal,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,card,mobile_money,insurance'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        $payment = DB::transaction(function () use ($data, $request): Payment {
            $invoice = Invoice::withSum('payments', 'amount')->lockForUpdate()->findOrFail($data['invoice_id']);
            if (! empty($data['reference']) && Payment::where('payment_method', $data['payment_method'])->where('reference', $data['reference'])->exists()) {
                abort(422, 'This payment reference has already been recorded.');
            }
            $paid = (float) ($invoice->payments_sum_amount ?? 0);
            $remaining = (float) $invoice->total_amount - $paid;

            abort_if((float) $data['amount'] > $remaining, 422, 'Payment exceeds the invoice balance.');

            $payment = Payment::create([
                ...$data,
                'received_by' => $request->user()->id,
                'paid_at' => now(),
                'status' => 'completed',
            ]);

            $newPaid = $paid + (float) $data['amount'];
            $invoice->update(['status' => $newPaid >= (float) $invoice->total_amount ? 'paid' : 'partially_paid']);

            AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'payments', 'model_type' => Payment::class, 'model_id' => $payment->id, 'description' => 'Payment recorded for invoice '.$invoice->invoice_number, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

            return $payment;
        });

        return redirect()->route('payments.index')->with('success', 'Payment recorded successfully.');
    }

    public function receipt(Payment $payment): View
    {
        $payment->load('invoice.patient', 'receivedBy');

        return view('payments.receipt', compact('payment'));
    }

    public function receiptPdf(Payment $payment): \Symfony\Component\HttpFoundation\Response
    {
        $payment->load('invoice.patient', 'receivedBy');
        return Pdf::loadView('payments.receipt-pdf', compact('payment'))->download('receipt-'.$payment->id.'.pdf');
    }
}
