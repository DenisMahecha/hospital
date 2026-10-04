<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Notification;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(\Illuminate\Http\Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        return view('inventory.index', [
            'items' => InventoryItem::with('supplier')->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')->orWhere('item_code', 'like', '%'.$search.'%')->orWhere('category', 'like', '%'.$search.'%');
            })->latest()->paginate(25)->withQueryString(),
            'search' => $search,
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['item_code' => ['required', 'string', 'max:100', 'unique:inventory_items,item_code'], 'name' => ['required', 'string', 'max:150'], 'category' => ['nullable', 'string', 'max:100'], 'unit' => ['required', 'string', 'max:50'], 'reorder_level' => ['required', 'integer', 'min:0'], 'supplier_id' => ['nullable', 'exists:suppliers,id']]);
        $item = InventoryItem::create($data + ['current_stock' => 0, 'status' => 'active']);
        $this->audit($request, $item, 'create', 'Inventory item created.');

        return back()->with('success', 'Inventory item added.');
    }

    public function update(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $data = $request->validate(['item_code' => ['required', 'string', 'max:100', 'unique:inventory_items,item_code,'.$inventoryItem->id], 'name' => ['required', 'string', 'max:150'], 'category' => ['nullable', 'string', 'max:100'], 'unit' => ['required', 'string', 'max:50'], 'reorder_level' => ['required', 'integer', 'min:0'], 'status' => ['required', 'in:active,inactive'], 'supplier_id' => ['nullable', 'exists:suppliers,id']]);
        $inventoryItem->update($data);
        $this->audit($request, $inventoryItem, 'update', 'Inventory item updated.');

        return back()->with('success', 'Inventory item updated.');
    }

    public function adjust(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $data = $request->validate(['movement_type' => ['required', 'in:in,out,return,damaged,adjustment'], 'quantity' => ['required', 'integer', 'min:1'], 'remarks' => ['required', 'string', 'max:500'], 'reference' => ['nullable', 'string', 'max:100']]);
        if (in_array($data['movement_type'], ['out', 'damaged', 'adjustment'], true)) {
            abort_if($inventoryItem->current_stock < $data['quantity'], 422, 'Adjustment exceeds available stock.');
            $inventoryItem->decrement('current_stock', $data['quantity']);
        } else {
            $inventoryItem->increment('current_stock', $data['quantity']);
        }
        InventoryMovement::create(['inventory_item_id' => $inventoryItem->id, 'movement_type' => $data['movement_type'], 'quantity' => $data['quantity'], 'reference_type' => 'manual', 'reference_id' => null, 'remarks' => (! empty($data['reference']) ? $data['reference'].' - ' : '').$data['remarks'], 'moved_by' => $request->user()->id]);
        $this->audit($request, $inventoryItem, $data['movement_type'], 'Inventory stock movement recorded.');
        if ($inventoryItem->fresh()->current_stock <= $inventoryItem->reorder_level) {
            foreach (User::role('store-keeper')->get() as $storekeeper) {
                Notification::create(['user_id' => $storekeeper->id, 'title' => 'Low inventory stock', 'message' => $inventoryItem->name.' is at '.$inventoryItem->fresh()->current_stock.' '.$inventoryItem->unit.'.', 'type' => 'inventory_stock', 'metadata' => ['inventory_item_id' => $inventoryItem->id]]);
            }
        }

        return back()->with('success', 'Inventory movement recorded.');
    }

    public function alerts(): View
    {
        $items = InventoryItem::where('status', 'active')
            ->whereColumn('current_stock', '<=', 'reorder_level')
            ->orderByRaw('reorder_level - current_stock ASC')
            ->get();

        return view('inventory.alerts', ['items' => $items]);
    }

    public function history(): View
    {
        return view('inventory.history', ['movements' => InventoryMovement::with('inventoryItem', 'movedBy')->latest()->paginate(30)]);
    }

    private function audit(Request $request, InventoryItem $item, string $action, string $description): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => $action, 'module_name' => 'inventory', 'model_type' => InventoryItem::class, 'model_id' => $item->id, 'description' => $description, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }
}
