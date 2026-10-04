<?php

namespace App\Http\Controllers;

use App\Models\Drug;
use App\Models\AuditLog;
use App\Models\StockMovement;
use App\Models\Prescription;
use App\Models\Notification;
use App\Models\DrugBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PharmacyController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $drugs = Drug::when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('category', 'like', '%'.$search.'%'))->orderBy('name')->get();
        $prescriptions = Prescription::with('patient', 'dispensedDrug')->whereIn('status', ['active', 'partially_dispensed'])
            ->when($request->user()->hasRole('doctor'), fn ($query) => $query->where('prescribed_by', $request->user()->id))
            ->when($search !== '', fn ($query) => $query->where('prescription_number', 'like', '%'.$search.'%')->orWhere('medication_name', 'like', '%'.$search.'%'))->latest()->get();

        return view('pharmacy.index', [
            'drugs' => $drugs,
            'prescriptions' => $prescriptions,
            'patients' => $prescriptions->pluck('patient')->filter()->unique('id')->sortBy('first_name')->values(),
            'search' => $search,
            'lowStockDrugs' => Drug::where('status', 'active')->where('stock_quantity', '<', 20)->orderBy('stock_quantity')->get(),
            'expiringDrugs' => Drug::where('status', 'active')->whereNotNull('expiry_date')->whereBetween('expiry_date', [today(), today()->copy()->addDays(90)])->orderBy('expiry_date')->get(),
            'batches' => DrugBatch::with('drug')->where('quantity', '>', 0)->where('status', 'active')->orderBy('expiry_date')->get(),
            'movements' => StockMovement::with('drug', 'batch')->latest()->get(),
        ]);
    }

    public function alerts(): View
    {
        $lowStockDrugs = Drug::where('status', 'active')->where('stock_quantity', '<', 20)->orderBy('stock_quantity')->get();
        $expiringDrugs = Drug::where('status', 'active')->whereNotNull('expiry_date')->whereDate('expiry_date', '<=', now()->addDays(90))->orderBy('expiry_date')->get();

        return view('pharmacy.alerts', compact('lowStockDrugs', 'expiringDrugs'));
    }

    public function catalogue(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $drugs = Drug::withCount('batches')->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('category', 'like', '%'.$search.'%'))->orderBy('name')->paginate(25)->withQueryString();

        return view('pharmacy.catalogue', compact('drugs', 'search'));
    }

    public function history(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $movements = StockMovement::with('drug', 'batch')->when($search !== '', fn ($query) => $query->where('movement_type', 'like', '%'.$search.'%')->orWhere('reference', 'like', '%'.$search.'%')->orWhereHas('drug', fn ($drugQuery) => $drugQuery->where('name', 'like', '%'.$search.'%')))->latest()->paginate(30)->withQueryString();

        return view('pharmacy.history', compact('movements', 'search'));
    }

    public function receipt(Prescription $prescription): View
    {
        abort_unless(in_array($prescription->status, ['dispensed', 'partially_dispensed'], true), 404);
        return view('pharmacy.receipt', ['prescription' => $prescription->load('patient', 'dispensedBy', 'stockMovements.drug', 'stockMovements.batch')]);
    }

    public function storeDrug(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'category' => ['nullable', 'string', 'max:100'], 'form' => ['nullable', 'string', 'max:100'], 'unit_price' => ['required', 'numeric', 'min:0'], 'status' => ['required', 'in:active,inactive']]);
        $drug = Drug::create($data + ['stock_quantity' => 0]);
        $this->audit($request, $drug, 'create', 'Medicine catalogue item created.');

        return back()->with('success', 'Medicine added to the pharmacy catalogue.');
    }

    public function updateDrug(Request $request, Drug $drug): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'category' => ['nullable', 'string', 'max:100'], 'form' => ['nullable', 'string', 'max:100'], 'unit_price' => ['required', 'numeric', 'min:0'], 'status' => ['required', 'in:active,inactive']]);
        $drug->update($data);
        $this->audit($request, $drug, 'update', 'Medicine catalogue item updated.');

        return back()->with('success', 'Medicine catalogue item updated.');
    }

    public function dispense(Request $request): RedirectResponse
    {
        $data = $request->validate(['drug_id' => ['required', 'exists:drugs,id'], 'quantity' => ['required', 'integer', 'min:1'], 'prescription_id' => ['nullable', 'exists:prescriptions,id'], 'reference' => ['nullable', 'string'], 'notes' => ['nullable', 'string']]);
        $drug = Drug::findOrFail($data['drug_id']);
        $dispensedPrescriptionId = null;
        DB::transaction(function () use ($drug, $data, $request, &$dispensedPrescriptionId): void {
            $lockedDrug = Drug::whereKey($drug->id)->lockForUpdate()->firstOrFail();
            $prescription = ! empty($data['prescription_id']) ? Prescription::whereKey($data['prescription_id'])->lockForUpdate()->firstOrFail() : null;
            if ($prescription) {
                if (! in_array($prescription->status, ['active', 'partially_dispensed'], true)) {
                    throw ValidationException::withMessages(['prescription_id' => 'This prescription is not available for dispensing.']);
                }

                if (! $this->medicineNamesMatch($prescription->medication_name, $lockedDrug->name)) {
                    throw ValidationException::withMessages(['drug_id' => 'Selected medicine does not match the prescription. Use the same medicine as listed on the prescription, including dosage/form variants such as "Amoxicillin 500mg".']);
                }

                if ($prescription->dispensed_drug_id !== null && $prescription->dispensed_drug_id !== $lockedDrug->id) {
                    throw ValidationException::withMessages(['drug_id' => 'This prescription is already linked to another medicine.']);
                }

                if ($prescription->quantity_prescribed !== null && $prescription->quantity_dispensed + $data['quantity'] > $prescription->quantity_prescribed) {
                    throw ValidationException::withMessages(['quantity' => 'Dispensing quantity exceeds the prescription.']);
                }
            }
            abort_if($lockedDrug->status !== 'active', 422, 'This medicine is inactive.');
            abort_if($lockedDrug->stock_quantity < $data['quantity'], 422, 'Insufficient stock.');
            $batches = DrugBatch::where('drug_id', $lockedDrug->id)->where('status', 'active')->where('quantity', '>', 0)->whereDate('expiry_date', '>=', today())->orderBy('expiry_date')->lockForUpdate()->get();
            $batchedStock = (int) DrugBatch::where('drug_id', $lockedDrug->id)->sum('quantity');
            $legacyStock = max(0, $lockedDrug->stock_quantity - $batchedStock);
            $availableStock = $batches->sum('quantity') + $legacyStock;
            abort_if($availableStock < $data['quantity'], 422, 'Available non-expired stock is insufficient.');

            $remaining = $data['quantity'];
            foreach ($batches as $batch) {
                $issued = min($remaining, $batch->quantity);
                $batch->decrement('quantity', $issued);
                StockMovement::create(['drug_id' => $lockedDrug->id, 'drug_batch_id' => $batch->id, 'prescription_id' => $prescription?->id, 'movement_type' => 'out', 'quantity' => $issued, 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null]);
                $remaining -= $issued;
                if ($remaining === 0) {
                    break;
                }
            }
            if ($remaining > 0) {
                abort_if($lockedDrug->expiry_date?->isPast(), 422, 'This medicine has expired.');
                StockMovement::create(['drug_id' => $lockedDrug->id, 'prescription_id' => $prescription?->id, 'movement_type' => 'out', 'quantity' => $remaining, 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null]);
            }
            $lockedDrug->decrement('stock_quantity', $data['quantity']);
            $this->notifyLowStock($lockedDrug);
            if ($prescription) {
                $dispensedPrescriptionId = $prescription->id;
                $dispensedQuantity = $prescription->quantity_dispensed + $data['quantity'];
                $prescription->update(['dispensed_drug_id' => $lockedDrug->id, 'quantity_dispensed' => $dispensedQuantity, 'dispensed_at' => now(), 'dispensed_by' => $request->user()->id, 'status' => $prescription->quantity_prescribed !== null && $dispensedQuantity < $prescription->quantity_prescribed ? 'partially_dispensed' : 'dispensed']);
            }
            AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'pharmacy', 'model_type' => Drug::class, 'model_id' => $lockedDrug->id, 'description' => 'Medicine dispensed.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        });
        if ($dispensedPrescriptionId) {
            return redirect()->route('pharmacy.receipt', $dispensedPrescriptionId)->with('success', 'Medicine dispensed and receipt ready to print.');
        }
        return back()->with('success', 'Medicine dispensed and stock updated.');
    }

    public function receive(Request $request): RedirectResponse
    {
        $data = $request->validate(['drug_id' => ['required', 'exists:drugs,id'], 'quantity' => ['required', 'integer', 'min:1'], 'reference' => ['required', 'string', 'max:100'], 'batch_number' => ['nullable', 'string', 'max:100'], 'expiry_date' => ['required_with:batch_number', 'nullable', 'date', 'after:today'], 'notes' => ['nullable', 'string']]);

        DB::transaction(function () use ($data, $request): void {
            $drug = Drug::whereKey($data['drug_id'])->lockForUpdate()->firstOrFail();
            abort_if($drug->status !== 'active', 422, 'This medicine is inactive.');
            $drug->increment('stock_quantity', $data['quantity']);
            $batch = null;
            if (! empty($data['batch_number'])) {
                $batch = DrugBatch::firstOrCreate(['drug_id' => $drug->id, 'batch_number' => $data['batch_number']], ['quantity' => 0, 'expiry_date' => $data['expiry_date'], 'status' => 'active']);
                abort_if($batch->expiry_date->isPast(), 422, 'Cannot receive an expired batch.');
                $batch->increment('quantity', $data['quantity']);
            }
            if (! empty($data['expiry_date']) && (! $drug->expiry_date || $data['expiry_date'] < $drug->expiry_date->toDateString())) {
                $drug->update(['expiry_date' => $data['expiry_date']]);
            }
            StockMovement::create(['drug_id' => $drug->id, 'drug_batch_id' => $batch?->id, 'movement_type' => 'in', 'quantity' => $data['quantity'], 'reference' => $data['reference'], 'notes' => $data['notes'] ?? 'Pharmacy stock received.']);
            $this->audit($request, $drug, 'receive', 'Medicine stock received.');
        });

        return back()->with('success', 'Medicine stock received successfully.');
    }

    public function adjust(Request $request): RedirectResponse
    {
        $data = $request->validate(['drug_id' => ['required', 'exists:drugs,id'], 'movement_type' => ['required', 'in:return,adjustment'], 'quantity' => ['required', 'integer', 'min:1'], 'reference' => ['required', 'string', 'max:100'], 'notes' => ['required', 'string']]);

        DB::transaction(function () use ($data, $request): void {
            $drug = Drug::whereKey($data['drug_id'])->lockForUpdate()->firstOrFail();
            if ($data['movement_type'] === 'return') {
                $drug->increment('stock_quantity', $data['quantity']);
            } else {
                abort_if($drug->stock_quantity < $data['quantity'], 422, 'Adjustment exceeds available stock.');
                $drug->decrement('stock_quantity', $data['quantity']);
            }
            StockMovement::create(['drug_id' => $drug->id, 'movement_type' => $data['movement_type'], 'quantity' => $data['quantity'], 'reference' => $data['reference'], 'notes' => $data['notes']]);
            $this->audit($request, $drug, $data['movement_type'], $data['movement_type'] === 'return' ? 'Medicine returned to stock.' : 'Medicine removed from stock as damaged or adjusted.');
            $this->notifyLowStock($drug);
        });

        return back()->with('success', 'Pharmacy stock adjustment recorded.');
    }

    private function medicineNamesMatch(string $prescriptionMedication, string $drugName): bool
    {
        $prescriptionBase = $this->normalizeMedicineName($prescriptionMedication);
        $drugBase = $this->normalizeMedicineName($drugName);

        if ($prescriptionBase === '' || $drugBase === '') {
            return false;
        }

        if ($prescriptionBase === $drugBase) {
            return true;
        }

        return str_contains($prescriptionBase, $drugBase) || str_contains($drugBase, $prescriptionBase);
    }

    private function normalizeMedicineName(string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/\b(?:tablet|tablets|capsule|capsules|syrup|suspension|oral|injectable|solution|powder|drop|drops|ml|mg|mcg|g)\b/i', ' ', $normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized);

        return trim($normalized);
    }

    private function notifyLowStock(Drug $drug): void
    {
        if ($drug->stock_quantity >= 20) {
            Notification::where('type', 'pharmacy_stock')->whereNull('read_at')->where('metadata->drug_id', $drug->id)->delete();
            return;
        }
        if ($drug->stock_quantity < 20) {
            foreach (\App\Models\User::role('pharmacist')->get() as $pharmacist) {
                $alreadyNotified = Notification::where('user_id', $pharmacist->id)
                    ->where('type', 'pharmacy_stock')
                    ->whereNull('read_at')
                    ->where('metadata->drug_id', $drug->id)
                    ->exists();
                if ($alreadyNotified) {
                    continue;
                }
                Notification::create(['user_id' => $pharmacist->id, 'title' => 'Low pharmacy stock', 'message' => $drug->name.' has only '.$drug->stock_quantity.' units remaining.', 'type' => 'pharmacy_stock', 'metadata' => ['drug_id' => $drug->id]]);
            }
        }
    }

    private function audit(Request $request, Drug $drug, string $action, string $description): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => $action, 'module_name' => 'pharmacy', 'model_type' => Drug::class, 'model_id' => $drug->id, 'description' => $description, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }
}
