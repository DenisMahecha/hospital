<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View { return view('suppliers.index', ['suppliers' => Supplier::latest()->paginate(25)]); }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validate(['name' => ['required', 'string', 'max:255'], 'contact_person' => ['nullable', 'string'], 'phone' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'address' => ['nullable', 'string'], 'tax_number' => ['nullable', 'string']]));
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'suppliers', 'model_type' => Supplier::class, 'model_id' => $supplier->id, 'description' => 'Supplier created.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        return redirect()->route('suppliers.index')->with('success', 'Supplier added successfully.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'contact_person' => ['nullable', 'string'], 'phone' => ['nullable', 'string'], 'email' => ['nullable', 'email'], 'address' => ['nullable', 'string'], 'tax_number' => ['nullable', 'string'], 'status' => ['required', 'in:active,inactive']]);
        $supplier->update($data);
        AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'update', 'module_name' => 'suppliers', 'model_type' => Supplier::class, 'model_id' => $supplier->id, 'description' => 'Supplier updated.', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully.');
    }
}
