<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use App\Models\InventoryItem;
use App\Models\AuditLog;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Services\DailyNumberService;

class PurchaseOrderController extends Controller
{
    public function index(): View
    {
        return view('purchase-orders.index', ['orders' => PurchaseOrder::with('supplier', 'items.inventoryItem', 'items.drug')->latest()->paginate(25), 'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(), 'items' => InventoryItem::where('status', 'active')->orderBy('name')->limit(500)->get(), 'drugs' => Drug::where('status', 'active')->orderBy('name')->limit(500)->get()]);
    }

    public function store(Request $request, DailyNumberService $numberService): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
            'items.*.drug_id' => ['nullable', 'exists:drugs,id'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
            'items.*.expiry_date' => ['nullable', 'date', 'after:today'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);
        abort_unless(Supplier::whereKey($data['supplier_id'])->where('status', 'active')->exists(), 422, 'Only active suppliers can be used for purchase orders.');
        foreach ($data['items'] as $item) {
            abort_if(empty($item['inventory_item_id']) === empty($item['drug_id']), 422, 'Each purchase-order line must select exactly one inventory item or medicine.');
            if (! empty($item['inventory_item_id'])) {
                abort_unless(InventoryItem::whereKey($item['inventory_item_id'])->where('status', 'active')->exists(), 422, 'Only active inventory items can be ordered.');
            }
            if (! empty($item['drug_id'])) {
                abort_unless(Drug::whereKey($item['drug_id'])->where('status', 'active')->exists(), 422, 'Only active medicines can be ordered.');
            }
        }
        $data['po_number'] = $numberService->next('purchase_order', 'PO');

        DB::transaction(function () use ($data, $request): void {
            $order = PurchaseOrder::create(['po_number' => $data['po_number'], 'supplier_id' => $data['supplier_id'], 'ordered_by' => $request->user()->id, 'order_date' => today(), 'status' => 'draft', 'total_amount' => 0]);
            $total = 0;
            foreach ($data['items'] as $item) {
                abort_if(empty($item['inventory_item_id']) && empty($item['drug_id']), 422, 'Each purchase-order line must select an inventory item or medicine.');
                $lineTotal = (float) $item['quantity'] * (float) $item['unit_cost'];
                PurchaseOrderItem::create(['purchase_order_id' => $order->id, 'inventory_item_id' => $item['inventory_item_id'] ?? null, 'drug_id' => $item['drug_id'] ?? null, 'batch_number' => $item['batch_number'] ?? null, 'expiry_date' => $item['expiry_date'] ?? null, 'quantity' => $item['quantity'], 'unit_cost' => $item['unit_cost'], 'total_cost' => $lineTotal]);
                $total += $lineTotal;
            }
            $order->update(['total_amount' => $total]);
            AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'purchase_orders', 'model_type' => PurchaseOrder::class, 'model_id' => $order->id, 'description' => 'Purchase order created as draft.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        });
        return redirect()->route('purchase-orders.index')->with('success', 'Purchase order saved as draft.');
    }

    public function approve(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->status === 'draft', 422, 'Only draft purchase orders can be approved.');
        abort_if($purchaseOrder->items()->doesntExist(), 422, 'A purchase order must contain items before approval.');
        $purchaseOrder->update(['status' => 'approved']);
        AuditLog::create(['user_id' => request()->user()->id, 'user_type' => request()->user()::class, 'action' => 'approve', 'module_name' => 'purchase_orders', 'model_type' => PurchaseOrder::class, 'model_id' => $purchaseOrder->id, 'description' => 'Purchase order approved.', 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
        return redirect()->route('purchase-orders.index')->with('success', 'Purchase order approved.');
    }

    public function receive(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $request = request();
        DB::transaction(function () use ($purchaseOrder, $request): void {
            $lockedOrder = PurchaseOrder::with('items')->lockForUpdate()->findOrFail($purchaseOrder->id);
            abort_unless($lockedOrder->status === 'approved', 422, 'Only approved purchase orders can be received.');
            abort_if($lockedOrder->items->isEmpty(), 422, 'A purchase order must contain items before receiving.');

            foreach ($lockedOrder->items as $line) {
                if ($line->drug_id) {
                    abort_unless($line->batch_number && $line->expiry_date, 422, 'Medicine purchase lines require a batch number and expiry date.');
                    $batch = DrugBatch::firstOrCreate(['drug_id' => $line->drug_id, 'batch_number' => $line->batch_number], ['quantity' => 0, 'expiry_date' => $line->expiry_date, 'status' => 'active']);
                    abort_unless($batch->drug?->status === 'active', 422, 'Inactive medicines cannot be received.');
                    abort_if($batch->expiry_date->toDateString() !== $line->expiry_date->toDateString(), 422, 'The batch expiry date does not match the existing batch.');
                    abort_if($batch->expiry_date->isPast(), 422, 'Cannot receive an expired medicine batch.');
                    $batch->increment('quantity', $line->quantity);
                    $line->drug()->increment('stock_quantity', $line->quantity);
                    StockMovement::create(['drug_id' => $line->drug_id, 'drug_batch_id' => $batch->id, 'movement_type' => 'in', 'quantity' => $line->quantity, 'reference' => $lockedOrder->po_number, 'notes' => 'Received pharmacy purchase order.']);
                } else {
                    abort_unless($line->inventoryItem?->status === 'active', 422, 'Inactive inventory items cannot be received.');
                    $line->inventoryItem()->increment('current_stock', $line->quantity);
                    InventoryMovement::create(['inventory_item_id' => $line->inventory_item_id, 'movement_type' => 'in', 'quantity' => $line->quantity, 'reference_type' => 'purchase_order', 'reference_id' => $lockedOrder->id, 'remarks' => 'Received purchase order '.$lockedOrder->po_number, 'moved_by' => $request->user()->id]);
                }
            }
            $lockedOrder->update(['status' => 'received']);
            AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'receive', 'module_name' => 'purchase_orders', 'model_type' => PurchaseOrder::class, 'model_id' => $lockedOrder->id, 'description' => 'Purchase order received and stock posted.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        });
        return redirect()->route('purchase-orders.index')->with('success', 'Purchase order received and stock updated.');
    }
}
