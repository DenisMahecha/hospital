<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\Ward;
use Illuminate\View\View;

class ZoneDashboardController extends Controller
{
    public function index(): View
    {
        $zones = [
            'ICU' => Ward::where('zone', 'ICU')->withCount(['admissions as occupied_beds' => fn ($query) => $query->where('status', 'admitted')])->get(),
            'Labour & Maternity' => Ward::where('zone', 'Labour & Maternity')->withCount(['admissions as occupied_beds' => fn ($query) => $query->where('status', 'admitted')])->get(),
            'Paediatrics' => Ward::where('zone', 'Paediatrics')->withCount(['admissions as occupied_beds' => fn ($query) => $query->where('status', 'admitted')])->get(),
        ];

        $summary = [
            'ICU' => Admission::whereHas('ward', fn ($query) => $query->where('zone', 'ICU'))->where('status', 'admitted')->count(),
            'Labour & Maternity' => Admission::whereHas('ward', fn ($query) => $query->where('zone', 'Labour & Maternity'))->where('status', 'admitted')->count(),
            'Paediatrics' => Admission::whereHas('ward', fn ($query) => $query->where('zone', 'Paediatrics'))->where('status', 'admitted')->count(),
        ];

        return view('zones.dashboard', compact('zones', 'summary'));
    }
}
