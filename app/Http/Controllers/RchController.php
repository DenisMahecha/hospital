<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\VaccinationRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RchController extends Controller
{
    public function index(): View
    {
        return view('rch.index', [
            'vaccinations' => VaccinationRecord::latest()->paginate(25),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'child_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'vaccine_name' => ['required', 'string', 'max:255'],
            'dose_number' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:scheduled,completed,missed,overdue'],
        ]);

        $record = VaccinationRecord::create($data);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'user_type' => $request->user()::class,
            'action' => 'create',
            'module_name' => 'rch',
            'model_type' => VaccinationRecord::class,
            'model_id' => $record->id,
            'description' => 'RCH vaccination record created for '.$record->child_name.'.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('rch.index')->with('success', 'Vaccination record saved.');
    }
}
