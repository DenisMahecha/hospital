<?php

namespace App\Http\Controllers;

use App\Models\DeathRecord;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\LabResultRead;
use App\Models\RadiologyResultRead;
use App\Models\Ward;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        $tat = $this->labTatStats();
        $clinicalZones = $this->clinicalZoneSummary();
        $zoneCapacity = $this->clinicalZoneCapacitySummary();
        $mortality = $this->deathSummary();
        $causeBreakdown = $this->deathCauseBreakdown();

        return view('reports.index', [
            'summary' => [
                'revenue' => Payment::where('status', 'completed')->sum('amount'),
                'revenue_today' => Payment::where('status', 'completed')->whereDate('paid_at', today())->sum('amount'),
                'revenue_month' => Payment::where('status', 'completed')->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
                'patients' => Patient::count(),
                'lab_tests' => LabRequest::count(),
                'prescriptions' => Prescription::count(),
                'lab_completed' => $tat['count'],
                'lab_average_tat_minutes' => $tat['average'],
                'lab_fastest_tat_minutes' => $tat['fastest'],
                'lab_slowest_tat_minutes' => $tat['slowest'],
                'lab_result_reads' => LabResultRead::with('labRequest', 'user')->latest('read_at')->take(25)->get(),
                'radiology_result_reads' => RadiologyResultRead::with('radiologyRequest', 'user')->latest('read_at')->take(25)->get(),
                'clinical_zones' => $clinicalZones,
                'clinical_zone_capacity' => $zoneCapacity,
                'deaths_total' => $mortality['total'],
                'deaths_this_month' => $mortality['this_month'],
                'recent_deaths' => $mortality['recent'],
                'death_cause_breakdown' => $causeBreakdown,
            ],
        ]);
    }

    public function export(): StreamedResponse
    {
        $summary = [
            'revenue' => Payment::where('status', 'completed')->sum('amount'),
            'revenue_today' => Payment::where('status', 'completed')->whereDate('paid_at', today())->sum('amount'),
            'revenue_month' => Payment::where('status', 'completed')->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount'),
            'patients' => Patient::count(),
            'lab_tests' => LabRequest::count(),
            'prescriptions' => Prescription::count(),
        ];
        $tat = $this->labTatStats();
        $clinicalZones = $this->clinicalZoneSummary();
        $mortality = $this->deathSummary();

        return response()->streamDownload(function () use ($summary, $tat, $clinicalZones, $mortality): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Metric', 'Value']);
            foreach ($summary as $metric => $value) {
                fputcsv($handle, [$metric, $value]);
            }
            fputcsv($handle, ['lab_completed', $tat['count']]);
            fputcsv($handle, ['lab_average_tat_minutes', $tat['average']]);
            fputcsv($handle, ['lab_fastest_tat_minutes', $tat['fastest']]);
            fputcsv($handle, ['lab_slowest_tat_minutes', $tat['slowest']]);
            foreach ($clinicalZones as $zone => $value) {
                fputcsv($handle, ['clinical_zone_'.$zone, $value]);
            }
            fputcsv($handle, ['deaths_total', $mortality['total']]);
            fputcsv($handle, ['deaths_this_month', $mortality['this_month']]);
            fclose($handle);
        }, 'hospital-report-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function clinicalZoneSummary(): array
    {
        return [
            'ICU' => Ward::where('zone', 'ICU')->count(),
            'Labour & Maternity' => Ward::where('zone', 'Labour & Maternity')->count(),
            'Paediatrics' => Ward::where('zone', 'Paediatrics')->count(),
        ];
    }

    private function clinicalZoneCapacitySummary(): array
    {
        $zones = [
            'ICU',
            'Labour & Maternity',
            'Paediatrics',
        ];

        $summary = [];

        foreach ($zones as $zone) {
            $summary[$zone] = (int) Ward::where('zone', $zone)->sum('capacity');
        }

        return $summary;
    }

    private function deathSummary(): array
    {
        $recent = DeathRecord::with('patient')->latest('date_of_death')->take(10)->get();

        return [
            'total' => DeathRecord::count(),
            'this_month' => DeathRecord::whereMonth('date_of_death', now()->month)->whereYear('date_of_death', now()->year)->count(),
            'recent' => $recent,
        ];
    }

    private function deathCauseBreakdown(): array
    {
        return DeathRecord::selectRaw('cause, COUNT(*) as total')
            ->whereNotNull('cause')
            ->where('cause', '!=', '')
            ->groupBy('cause')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->mapWithKeys(fn ($row) => [$row->cause => (int) $row->total])
            ->all();
    }

    private function labTatStats(): array
    {
        $minutes = DB::connection()->getDriverName() === 'sqlite'
            ? '(julianday(completed_at) - julianday(created_at)) * 1440'
            : 'TIMESTAMPDIFF(MINUTE, created_at, completed_at)';
        $stats = LabRequest::where('status', 'completed')->whereNotNull('completed_at')->selectRaw('COUNT(*) as count, AVG('.$minutes.') as average, MIN('.$minutes.') as fastest, MAX('.$minutes.') as slowest')->first();

        return ['count' => (int) ($stats->count ?? 0), 'average' => (int) round($stats->average ?? 0), 'fastest' => (int) round($stats->fastest ?? 0), 'slowest' => (int) round($stats->slowest ?? 0)];
    }
}
