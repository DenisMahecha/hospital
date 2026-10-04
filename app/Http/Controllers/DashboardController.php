<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\BackupLog;
use App\Models\DeathRecord;
use App\Models\Drug;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\LabRequest;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PurchaseOrder;
use App\Models\RadiologyRequest;
use App\Models\Supplier;
use App\Models\Visit;
use App\Models\VaccinationRecord;
use App\Models\Ward;
use App\Models\User;
use App\Models\NursingNote;
use App\Models\Vital;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function doctorZone(): View
    {
        $appointments = Appointment::with('patient')
            ->where('doctor_id', auth()->id())
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->orderBy('scheduled_at')
            ->get();
        $assignedPatientIds = $appointments->pluck('patient_id')->unique();
        $visits = Visit::with(['patient', 'vitals'])
            ->where('doctor_id', auth()->id())
            ->latest('visit_date')
            ->take(12)
            ->get();
        $patients = Patient::whereIn('id', $assignedPatientIds)->orderBy('first_name')->get();
        $recentVitals = Vital::with('visit.patient')
            ->whereHas('visit', fn ($query) => $query->where('doctor_id', auth()->id()))
            ->latest()
            ->take(10)
            ->get();

        return view('doctor.zone', [
            'visits' => $visits,
            'patients' => $patients,
            'appointments' => $appointments,
            'recentVitals' => $recentVitals,
        ]);
    }

    public function index(): View
    {
        $user = auth()->user();
        $role = $user?->getRoleNames()->first() ?? 'guest';

        $workspaces = [
            'super-admin' => ['title' => 'Super Control Dashboard', 'description' => 'Full system control across every department', 'modules' => ['Users', 'Patients', 'Appointments', 'Inpatient units', 'Billing', 'Inventory', 'Laboratory', 'Radiology', 'Pharmacy', 'HR', 'Reports', 'Audit logs', 'System settings']],
            'hospital-manager' => ['title' => 'Hospital Manager Dashboard', 'description' => 'Operations workspace', 'modules' => ['Patients', 'Appointments', 'Inpatient units', 'Billing', 'Reports', 'Audit logs']],
            'receptionist' => ['title' => 'Reception Dashboard', 'description' => 'Front desk workspace', 'modules' => ['Patients', 'Appointments', 'Billing', 'Payments']],
            'doctor' => ['title' => 'Doctor Dashboard', 'description' => 'Clinical workspace', 'modules' => ['Patients', 'Appointments', 'Visits', 'Lab', 'Radiology']],
            'nurse' => ['title' => 'Nurse IPD Dashboard', 'description' => 'Inpatient nursing care workspace', 'modules' => ['Admissions', 'Wards', 'Nursing Notes']],
            'pharmacist' => ['title' => 'Pharmacy Dashboard', 'description' => 'Medication workspace', 'modules' => ['Pharmacy', 'Inventory', 'Stock movements', 'Reports']],
            'lab-technician' => ['title' => 'Laboratory Dashboard', 'description' => 'Diagnostics workspace', 'modules' => ['Lab requests', 'Results', 'Patients', 'Reports']],
            'cashier' => ['title' => 'Billing Dashboard', 'description' => 'Revenue workspace', 'modules' => ['Billing', 'Invoices', 'Insurance claims', 'Reports']],
            'hr' => ['title' => 'HR Dashboard', 'description' => 'People & payroll workspace', 'modules' => ['Employees', 'Payroll', 'Reports', 'Staff access']],
            'store-keeper' => ['title' => 'Store Dashboard', 'description' => 'Supply chain workspace', 'modules' => ['Inventory', 'Suppliers', 'Purchase orders', 'Stock movements']],
            'radiologist' => ['title' => 'Radiology Dashboard', 'description' => 'Imaging workspace', 'modules' => ['Radiology', 'Patients', 'Reports', 'Queue']],
            'patient' => ['title' => 'Patient Dashboard', 'description' => 'Personal health workspace', 'modules' => ['Appointments', 'Visits', 'Invoices', 'Notifications']],
        ];

        $workspace = $workspaces[$role] ?? [
            'title' => 'Hospital Dashboard',
            'description' => 'Hospital operations workspace',
            'modules' => ['Patients', 'Appointments', 'Reports'],
        ];

        $actions = match ($role) {
            'super-admin' => [['label' => 'Users & roles', 'route' => 'users.index'], ['label' => 'Inpatient units', 'route' => 'wards.index'], ['label' => 'Billing', 'route' => 'billing.index'], ['label' => 'Reports', 'route' => 'reports.index']],
            'hospital-manager' => [['label' => 'Inpatient units', 'route' => 'wards.index'], ['label' => 'Clinical zones', 'route' => 'zones.index'], ['label' => 'Death records', 'route' => 'deaths.index'], ['label' => 'Billing overview', 'route' => 'billing.index'], ['label' => 'Reports', 'route' => 'reports.index'], ['label' => 'Audit logs', 'route' => 'audit-logs.index']],
            'receptionist' => [['label' => 'Register patient', 'route' => 'patients.create'], ['label' => 'Appointments', 'route' => 'appointments.index']],
            'doctor' => [['label' => 'Consultations', 'route' => 'visits.index'], ['label' => 'Laboratory', 'route' => 'lab.index'], ['label' => 'Radiology', 'route' => 'radiology.index']],
            'nurse' => [['label' => 'Admissions', 'route' => 'admissions.index'], ['label' => 'Wards', 'route' => 'wards.index'], ['label' => 'Nursing care', 'route' => 'nursing-care.index'], ['label' => 'Deaths & mortality', 'route' => 'deaths.index']],
            'pharmacist' => [['label' => 'Pharmacy', 'route' => 'pharmacy.index'], ['label' => 'Prescriptions', 'route' => 'prescriptions.index'], ['label' => 'Reports', 'route' => 'reports.index']],
            'lab-technician' => [['label' => 'Laboratory', 'route' => 'lab.index'], ['label' => 'Reports', 'route' => 'reports.index']],
            'cashier' => [['label' => 'Create / view invoices', 'route' => 'billing.index'], ['label' => 'Record payments', 'route' => 'payments.index'], ['label' => 'Insurance claims', 'route' => 'insurance.index'], ['label' => 'Financial reports', 'route' => 'reports.index']],
            'hr' => [['label' => 'Employees & payroll', 'route' => 'hr.index'], ['label' => 'Reports', 'route' => 'reports.index']],
            'store-keeper' => [['label' => 'Inventory', 'route' => 'inventory.index'], ['label' => 'Purchase orders', 'route' => 'purchase-orders.index']],
            'radiologist' => [['label' => 'Radiology queue', 'route' => 'radiology.index'], ['label' => 'Reports', 'route' => 'reports.index']],
            'patient' => [['label' => 'Patient portal', 'route' => 'portal.index'], ['label' => 'Notifications', 'route' => 'notifications.index']],
            default => [['label' => 'Dashboard', 'route' => 'dashboard']],
        };

        $stats = match ($role) {
            'super-admin' => [
                ['label' => 'Users', 'value' => User::count(), 'note' => 'Accounts provisioned'],
                ['label' => 'Audit events', 'value' => AuditLog::whereDate('created_at', today())->count(), 'note' => 'Logged today'],
                ['label' => 'Revenue today', 'value' => 'TZS '.number_format(Payment::whereDate('paid_at', today())->sum('amount')), 'note' => 'Completed payments'],
                ['label' => 'Backups', 'value' => BackupLog::where('status', 'success')->count(), 'note' => 'Successful backups'],
            ],
            'hospital-manager' => [
                ['label' => 'Patients', 'value' => Patient::count(), 'note' => 'Total active registry'],
                ['label' => 'Appointments today', 'value' => Appointment::whereDate('scheduled_at', today())->count(), 'note' => 'Front desk schedule'],
                ['label' => 'Pending invoices', 'value' => Invoice::whereIn('status', ['pending', 'partially_paid'])->count(), 'note' => 'Collections needed'],
                ['label' => 'Low stock alerts', 'value' => InventoryItem::whereColumn('current_stock', '<=', 'reorder_level')->count(), 'note' => 'Inventory risk'],
            ],
            'receptionist' => [
                ['label' => 'Appointments today', 'value' => Appointment::whereDate('scheduled_at', today())->count(), 'note' => 'Scheduled visits'],
                ['label' => 'New patients', 'value' => Patient::whereDate('created_at', today())->count(), 'note' => 'Registrations today'],
                ['label' => 'Pending billing', 'value' => Invoice::whereIn('status', ['pending', 'partially_paid'])->count(), 'note' => 'Awaiting payment'],
                ['label' => 'Open bookings', 'value' => Appointment::whereDate('scheduled_at', today())->whereIn('status', ['scheduled', 'confirmed', 'checked_in'])->count(), 'note' => 'Today\'s scheduled visits'],
            ],
            'doctor' => [
                ['label' => 'Patients today', 'value' => Patient::whereDate('created_at', today())->count(), 'note' => 'Clinical registrations'],
                ['label' => 'Appointments', 'value' => Appointment::whereDate('scheduled_at', today())->count(), 'note' => 'Today\'s consultations'],
                ['label' => 'Lab queue', 'value' => LabRequest::whereIn('status', ['pending', 'in_progress'])->count(), 'note' => 'Awaiting results'],
                ['label' => 'Prescriptions', 'value' => Prescription::where('status', 'active')->count(), 'note' => 'Active orders'],
            ],
            'nurse' => $this->nurseStats(),
            'pharmacist' => [
                ['label' => 'Medicines', 'value' => Drug::where('status', 'active')->count(), 'note' => 'Active catalogue'],
                ['label' => 'Low stock', 'value' => InventoryItem::whereColumn('current_stock', '<=', 'reorder_level')->count(), 'note' => 'Needs replenishment'],
                ['label' => 'Expiring medicines', 'value' => Drug::where('status', 'active')->whereNotNull('expiry_date')->whereBetween('expiry_date', [today(), today()->addDays(90)])->count(), 'note' => 'Within 90 days'],
                ['label' => 'Prescriptions', 'value' => Prescription::where('status', 'active')->count(), 'note' => 'Active orders'],
                ['label' => 'Inventory items', 'value' => InventoryItem::count(), 'note' => 'Store catalogue'],
            ],
            'lab-technician' => [
                ['label' => 'Pending tests', 'value' => LabRequest::where('status', 'pending')->count(), 'note' => 'Ready for processing'],
                ['label' => 'In progress', 'value' => LabRequest::where('status', 'in_progress')->count(), 'note' => 'Currently running'],
                ['label' => 'Requests today', 'value' => LabRequest::whereDate('created_at', today())->count(), 'note' => 'New workload'],
                ['label' => 'Patients today', 'value' => Patient::whereDate('created_at', today())->count(), 'note' => 'Registry activity'],
            ],
            'cashier' => [
                ['label' => 'Pending invoices', 'value' => Invoice::whereIn('status', ['pending', 'partially_paid'])->count(), 'note' => 'Need collection'],
                ['label' => 'Revenue today', 'value' => 'TZS '.number_format(Payment::whereDate('paid_at', today())->sum('amount')), 'note' => 'Completed payments'],
                ['label' => 'Payments today', 'value' => Payment::whereDate('paid_at', today())->count(), 'note' => 'Receipts issued'],
                ['label' => 'Invoices', 'value' => Invoice::count(), 'note' => 'Total billing records'],
            ],
            'store-keeper' => [
                ['label' => 'Low stock', 'value' => InventoryItem::whereColumn('current_stock', '<=', 'reorder_level')->count(), 'note' => 'Replenishment alerts'],
                ['label' => 'Suppliers', 'value' => Supplier::where('status', 'active')->count(), 'note' => 'Active partners'],
                ['label' => 'Draft orders', 'value' => PurchaseOrder::where('status', 'draft')->count(), 'note' => 'Awaiting approval'],
                ['label' => 'Movements today', 'value' => InventoryMovement::whereDate('created_at', today())->count(), 'note' => 'Stock activity'],
            ],
            'hr' => [
                ['label' => 'Employees', 'value' => Employee::count(), 'note' => 'People directory'],
                ['label' => 'Active staff', 'value' => Employee::where('status', 'active')->count(), 'note' => 'Currently employed'],
                ['label' => 'Payroll entries', 'value' => \App\Models\Payroll::where('month', now()->format('Y-m'))->count(), 'note' => 'Current month'],
            ],
            'radiologist' => [
                ['label' => 'Pending imaging', 'value' => RadiologyRequest::where('status', 'pending')->count(), 'note' => 'Awaiting review'],
                ['label' => 'Requests today', 'value' => RadiologyRequest::whereDate('created_at', today())->count(), 'note' => 'New imaging work'],
                ['label' => 'Patients today', 'value' => Patient::whereDate('created_at', today())->count(), 'note' => 'Registry activity'],
                ['label' => 'Reports', 'value' => RadiologyRequest::where('status', 'completed')->count(), 'note' => 'Completed studies'],
            ],
            'patient' => $this->patientStats($user),
            default => [
                ['label' => 'Users', 'value' => User::count(), 'note' => 'Accounts provisioned'],
                ['label' => 'Audit events', 'value' => AuditLog::whereDate('created_at', today())->count(), 'note' => 'Logged today'],
                ['label' => 'Revenue today', 'value' => 'TZS '.number_format(Payment::whereDate('paid_at', today())->sum('amount')), 'note' => 'Completed payments'],
                ['label' => 'Backups', 'value' => BackupLog::where('status', 'success')->count(), 'note' => 'Successful backups'],
            ],
        };

        $unreadNotificationCount = Notification::where('user_id', $user->id)->whereNull('read_at')->count();
        $stats[] = [
            'label' => 'Unread notifications',
            'value' => $unreadNotificationCount,
            'note' => $unreadNotificationCount > 0 ? 'New updates awaiting review' : 'No unread notification',
        ];

        $nurseData = $role === 'nurse' ? $this->nurseData() : null;
        $managementSummary = in_array($role, ['super-admin', 'hospital-manager'], true)
            ? $this->managementSummary()
            : null;
        $operationsSummary = in_array($role, ['super-admin', 'hospital-manager'], true)
            ? $this->clinicalOperationsSummary()
            : null;
        $patientFlowSummary = in_array($role, ['super-admin', 'hospital-manager'], true)
            ? $this->patientFlowSummary()
            : null;
        $leadershipAlerts = in_array($role, ['super-admin', 'hospital-manager'], true)
            ? $this->leadershipAlerts()
            : null;
        $recentAdmissionsWatchlist = in_array($role, ['super-admin', 'hospital-manager'], true)
            ? $this->recentAdmissionsWatchlist()
            : null;
        $operationalBrief = in_array($role, ['super-admin', 'hospital-manager'], true)
            ? $this->operationalBrief()
            : null;
        $departmentTrendOverview = in_array($role, ['super-admin', 'hospital-manager'], true)
            ? $this->departmentTrendOverview()
            : null;
        $performanceScorecard = in_array($role, ['super-admin', 'hospital-manager'], true)
            ? $this->performanceScorecard()
            : null;
        $executiveBoardSummary = in_array($role, ['super-admin', 'hospital-manager'], true)
            ? $this->executiveBoardSummary()
            : null;
        $operationalActionQueue = in_array($role, ['super-admin', 'hospital-manager'], true)
            ? $this->operationalActionQueue()
            : null;

        return view('dashboard', compact('workspace', 'stats', 'actions', 'nurseData', 'managementSummary', 'operationsSummary', 'patientFlowSummary', 'leadershipAlerts', 'recentAdmissionsWatchlist', 'operationalBrief', 'departmentTrendOverview', 'performanceScorecard', 'executiveBoardSummary', 'operationalActionQueue'));
    }

    private function managementSummary(): array
    {
        return [
            'clinical_zones' => [
                'ICU' => Ward::where('zone', 'ICU')->count(),
                'Labour & Maternity' => Ward::where('zone', 'Labour & Maternity')->count(),
                'Paediatrics' => Ward::where('zone', 'Paediatrics')->count(),
            ],
            'deaths_total' => DeathRecord::count(),
            'deaths_this_month' => DeathRecord::whereMonth('date_of_death', now()->month)
                ->whereYear('date_of_death', now()->year)
                ->count(),
        ];
    }

    private function clinicalOperationsSummary(): array
    {
        $zones = [
            'ICU' => ['capacity' => Ward::where('zone', 'ICU')->sum('capacity'), 'occupied' => Admission::whereHas('ward', fn ($query) => $query->where('zone', 'ICU'))->where('status', 'admitted')->count()],
            'Labour & Maternity' => ['capacity' => Ward::where('zone', 'Labour & Maternity')->sum('capacity'), 'occupied' => Admission::whereHas('ward', fn ($query) => $query->where('zone', 'Labour & Maternity'))->where('status', 'admitted')->count()],
            'Paediatrics' => ['capacity' => Ward::where('zone', 'Paediatrics')->sum('capacity'), 'occupied' => Admission::whereHas('ward', fn ($query) => $query->where('zone', 'Paediatrics'))->where('status', 'admitted')->count()],
        ];

        $summary = [];
        foreach ($zones as $zone => $data) {
            $capacity = (int) ($data['capacity'] ?? 0);
            $occupied = (int) ($data['occupied'] ?? 0);
            $percentage = $capacity > 0 ? round(($occupied / $capacity) * 100, 0) : 0;
            $summary[] = [
                'zone' => $zone,
                'capacity' => $capacity,
                'occupied' => $occupied,
                'percentage' => $percentage,
                'available' => max(0, $capacity - $occupied),
            ];
        }

        return $summary;
    }

    private function patientFlowSummary(): array
    {
        return [
            'admissions_today' => Admission::whereDate('admission_date', today())->count(),
            'discharges_today' => Admission::whereNotNull('discharged_at')->whereDate('discharged_at', today())->count(),
            'active_admissions' => Admission::where('status', 'admitted')->count(),
            'deaths_this_month' => DeathRecord::whereMonth('date_of_death', now()->month)->whereYear('date_of_death', now()->year)->count(),
        ];
    }

    private function leadershipAlerts(): array
    {
        $zones = [
            'ICU' => ['capacity' => Ward::where('zone', 'ICU')->sum('capacity'), 'occupied' => Admission::whereHas('ward', fn ($query) => $query->where('zone', 'ICU'))->where('status', 'admitted')->count()],
            'Labour & Maternity' => ['capacity' => Ward::where('zone', 'Labour & Maternity')->sum('capacity'), 'occupied' => Admission::whereHas('ward', fn ($query) => $query->where('zone', 'Labour & Maternity'))->where('status', 'admitted')->count()],
            'Paediatrics' => ['capacity' => Ward::where('zone', 'Paediatrics')->sum('capacity'), 'occupied' => Admission::whereHas('ward', fn ($query) => $query->where('zone', 'Paediatrics'))->where('status', 'admitted')->count()],
        ];

        $alerts = [];
        foreach ($zones as $zone => $data) {
            $capacity = (int) ($data['capacity'] ?? 0);
            $occupied = (int) ($data['occupied'] ?? 0);
            $percentage = $capacity > 0 ? round(($occupied / $capacity) * 100, 0) : 0;

            if (($zone === 'ICU' && $percentage >= 50) || $percentage >= 80) {
                $status = 'Critical';
                $action = 'Priority action';
            } elseif ($percentage >= 60) {
                $status = 'Watch';
                $action = 'Escalate staffing';
            } else {
                $status = 'Stable';
                $action = 'Continue monitoring';
            }

            if ($capacity > 0) {
                $alerts[] = [
                    'zone' => $zone,
                    'status' => $status,
                    'percentage' => $percentage,
                    'details' => $occupied.' / '.$capacity.' occupied',
                    'action' => $action,
                ];
            }
        }

        return $alerts;
    }

    private function recentAdmissionsWatchlist(): \Illuminate\Database\Eloquent\Collection
    {
        return Admission::with(['patient', 'ward'])
            ->where('status', 'admitted')
            ->latest('admission_date')
            ->take(6)
            ->get();
    }

    private function operationalBrief(): array
    {
        $zoneLoad = $this->clinicalOperationsSummary();
        $averageOccupancy = count($zoneLoad) > 0 ? (int) round(array_sum(array_column($zoneLoad, 'percentage')) / count($zoneLoad)) : 0;
        $highestPressure = collect($zoneLoad)->sortByDesc('percentage')->first();
        $alerts = $this->leadershipAlerts();
        $criticalZone = collect($alerts)->firstWhere('status', 'Critical');

        return [
            'average_zone_occupancy' => $averageOccupancy,
            'highest_pressure_zone' => $highestPressure['zone'] ?? 'N/A',
            'highest_pressure_value' => $highestPressure['percentage'] ?? 0,
            'critical_zone' => $criticalZone['zone'] ?? 'N/A',
            'active_admissions' => Admission::where('status', 'admitted')->count(),
        ];
    }

    private function departmentTrendOverview(): array
    {
        $sevenDaySeries = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $sevenDaySeries[] = [
                'date' => $date->format('d M'),
                'admissions' => Admission::whereDate('admission_date', $date->toDateString())->count(),
            ];
        }

        $byZone = [];
        foreach (['ICU', 'Labour & Maternity', 'Paediatrics'] as $zone) {
            $byZone[$zone] = Admission::whereHas('ward', fn ($query) => $query->where('zone', $zone))
                ->whereDate('admission_date', '>=', now()->subDays(7)->toDateString())
                ->count();
        }

        return [
            'seven_day_admissions' => $sevenDaySeries,
            'by_zone' => $byZone,
        ];
    }

    private function performanceScorecard(): array
    {
        $allAdmissions = Admission::whereNotNull('admission_date')->get();
        $discharged = Admission::whereNotNull('discharged_at')->get();

        $lengthOfStay = [];
        foreach ($allAdmissions as $admission) {
            $start = $admission->admission_date;
            $end = $admission->discharged_at ?? now();
            if ($start && $end) {
                $lengthOfStay[] = $start->diffInDays($end);
            }
        }

        $avgLengthOfStay = count($lengthOfStay) > 0 ? round(array_sum($lengthOfStay) / count($lengthOfStay), 1) : 0;
        $dischargeRate = $allAdmissions->count() > 0 ? round(($discharged->count() / $allAdmissions->count()) * 100, 1) : 0;

        $byZone = [];
        foreach (['ICU', 'Labour & Maternity', 'Paediatrics'] as $zone) {
            $zoneAdmissions = Admission::whereHas('ward', fn ($query) => $query->where('zone', $zone))->count();
            $byZone[$zone] = [
                'admissions' => $zoneAdmissions,
                'discharges' => Admission::whereHas('ward', fn ($query) => $query->where('zone', $zone))->whereNotNull('discharged_at')->count(),
            ];
        }

        return [
            'average_length_of_stay' => $avgLengthOfStay,
            'discharge_rate' => $dischargeRate,
            'by_zone' => $byZone,
        ];
    }

    private function executiveBoardSummary(): array
    {
        $operationsSummary = $this->clinicalOperationsSummary();
        $criticalUnits = collect($this->leadershipAlerts())->filter(fn ($alert) => $alert['status'] === 'Critical')->values()->all();
        $bedUtilisation = collect($operationsSummary)->avg('percentage') ?? 0;

        return [
            'bed_utilisation' => round($bedUtilisation, 1),
            'critical_units' => $criticalUnits,
            'active_admissions' => Admission::where('status', 'admitted')->count(),
            'deaths_this_month' => DeathRecord::whereMonth('date_of_death', now()->month)->whereYear('date_of_death', now()->year)->count(),
        ];
    }

    private function operationalActionQueue(): array
    {
        $alerts = $this->leadershipAlerts();
        $queue = [];

        foreach ($alerts as $alert) {
            if ($alert['status'] === 'Critical') {
                $queue[] = [
                    'zone' => $alert['zone'],
                    'priority' => 'Immediate staffing review',
                    'detail' => $alert['details'].' · '.$alert['action'],
                ];
            }
        }

        if (empty($queue)) {
            $queue[] = [
                'zone' => 'General operations',
                'priority' => 'Routine monitoring',
                'detail' => 'No critical capacity alerts at this time.',
            ];
        }

        return ['items' => $queue];
    }

    private function nurseStats(): array
    {
        $occupied = Admission::where('status', 'admitted')->count();
        $capacity = Ward::sum('capacity');

        return [
            ['label' => 'Patients admitted', 'value' => $occupied, 'note' => 'Currently in IPD'],
            ['label' => 'Available beds', 'value' => max(0, $capacity - $occupied), 'note' => 'Across active wards'],
            ['label' => 'Wards', 'value' => Ward::count(), 'note' => 'Configured inpatient units'],
            ['label' => 'Notes today', 'value' => NursingNote::whereDate('recorded_at', today())->count(), 'note' => 'Nursing care entries'],
        ];
    }

    private function nurseData(): array
    {
        return [
            'admissions' => Admission::with('patient', 'ward')->where('status', 'admitted')->latest('admission_date')->take(10)->get(),
            'wards' => Ward::withCount(['admissions as occupied_beds' => fn ($query) => $query->where('status', 'admitted')])->orderBy('name')->get(),
            'notes' => NursingNote::with('admission.patient')->whereDate('recorded_at', today())->latest('recorded_at')->take(8)->get(),
        ];
    }

    private function patientStats(User $user): array
    {
        $patientId = $user->patientPortalAccounts()->value('patient_id');

        return [
            ['label' => 'Appointments', 'value' => $patientId ? Appointment::where('patient_id', $patientId)->count() : 0, 'note' => 'Your bookings'],
            ['label' => 'Prescriptions', 'value' => $patientId ? Prescription::where('patient_id', $patientId)->where('status', 'active')->count() : 0, 'note' => 'Active orders'],
            ['label' => 'Invoices', 'value' => $patientId ? Invoice::where('patient_id', $patientId)->count() : 0, 'note' => 'Your billing records'],
            ['label' => 'Notifications', 'value' => Notification::where('user_id', $user->id)->whereNull('read_at')->count(), 'note' => 'Unread updates'],
        ];
    }
}
