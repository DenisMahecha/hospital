<?php

namespace App\Services;

use App\Models\Drug;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\DailyNumberService;

class JourneyBillingService
{
    public function __construct(private DailyNumberService $numberService) {}

    public function calculate(Patient $patient): Collection
    {
        $items = collect();

        foreach ($patient->visits()->where('status', 'completed')->get() as $visit) {
            if (! $this->alreadyBilled('consultation', $visit->id, $patient)) {
                $items->push($this->item('consultation', 'OPD consultation '.$visit->visit_number, config('billing.prices.consultation'), $visit->id));
            }
        }

        foreach ($patient->labRequests()->where('status', 'completed')->get() as $request) {
            if (! $this->alreadyBilled('lab', $request->id, $patient)) {
                $items->push($this->item('lab', 'Laboratory: '.$request->test_name, (float) ($request->billing_amount ?? config('billing.prices.lab_default')), $request->id));
            }
        }

        foreach ($patient->radiologyRequests()->where('status', 'completed')->get() as $request) {
            if (! $this->alreadyBilled('radiology', $request->id, $patient)) {
                $price = config('billing.prices.radiology.'.$request->modality, config('billing.prices.lab_default'));
                $items->push($this->item('radiology', 'Radiology: '.$request->modality, $price, $request->id));
            }
        }

        foreach ($patient->prescriptions()->whereIn('status', ['active', 'partially_dispensed', 'dispensed'])->get() as $prescription) {
            if (! $this->alreadyBilled('medicine', $prescription->id, $patient)) {
                $drug = Drug::where('name', $prescription->medication_name)->first();
                $price = (float) ($drug?->unit_price ?? 0);
                if ($price > 0) {
                    $quantity = max(1, (int) ($prescription->quantity_dispensed ?? $prescription->quantity_prescribed ?? 1));
                    $items->push($this->item('medicine', 'Medicine: '.$prescription->medication_name, $price, $prescription->id, $quantity));
                }
            }
        }

        foreach ($patient->admissions()->get() as $admission) {
            if (! $this->alreadyBilled('admission', $admission->id, $patient)) {
                $items->push($this->item('admission', 'IPD admission '.$admission->admission_number, config('billing.prices.admission'), $admission->id));
            }
            if (! $this->alreadyBilled('bed_day', $admission->id, $patient)) {
                $start = $admission->admission_date;
                $end = $admission->status === 'admitted' ? now() : $admission->updated_at;
                $days = max(1, $start->diffInDays($end));
                $items->push($this->item('bed_day', 'IPD bed care: '.$days.' day(s)', config('billing.prices.bed_per_day'), $admission->id, $days));
            }
        }

        foreach ($patient->emergencyCases()->where('status', 'closed')->get() as $case) {
            if (! $this->alreadyBilled('emergency', $case->id, $patient)) {
                $items->push($this->item('emergency', 'Emergency triage: '.strtoupper($case->priority), config('billing.prices.emergency_triage'), $case->id));
            }
        }

        return $items;
    }

    public function createInvoice(Patient $patient, int $userId): Invoice
    {
        $items = $this->calculate($patient);
        abort_if($items->isEmpty(), 422, 'There are no new completed services to bill for this patient.');

        return DB::transaction(function () use ($patient, $items, $userId): Invoice {
            $invoice = Invoice::create([
                'invoice_number' => $this->numberService->next('invoice', 'INV'),
                'patient_id' => $patient->id,
                'service_description' => 'Automatic billing from completed patient journey',
                'total_amount' => $items->sum('total_price'),
                'status' => 'pending',
            ]);

            $invoice->items()->createMany($items->all());

            return $invoice;
        });
    }

    private function item(string $type, string $description, float $unitPrice, int $sourceId, int $quantity = 1): array
    {
        return ['item_type' => $type, 'description' => $description, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'total_price' => $unitPrice * $quantity, 'source_id' => $sourceId];
    }

    private function alreadyBilled(string $type, int $sourceId, Patient $patient): bool
    {
        return InvoiceItem::where('item_type', $type)->where('source_id', $sourceId)->whereHas('invoice', fn ($query) => $query->where('patient_id', $patient->id))->exists();
    }
}