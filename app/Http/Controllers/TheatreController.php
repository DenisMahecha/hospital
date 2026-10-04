<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\TheatreSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TheatreController extends Controller
{
    public function index(): View
    {
        return view('theatre.index', [
            'schedules' => TheatreSchedule::latest()->paginate(25),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'surgery_name' => ['required', 'string', 'max:255'],
            'scheduled_date' => ['required', 'date'],
            'theatre_room' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:scheduled,confirmed,in_progress,completed,cancelled'],
        ]);

        $schedule = TheatreSchedule::create($data);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'user_type' => $request->user()::class,
            'action' => 'create',
            'module_name' => 'theatre',
            'model_type' => TheatreSchedule::class,
            'model_id' => $schedule->id,
            'description' => 'Theatre schedule created for '.$schedule->surgery_name.'.',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('theatre.index')->with('success', 'Theatre schedule saved.');
    }
}
